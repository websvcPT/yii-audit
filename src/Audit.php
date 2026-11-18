<?php

namespace websvc\audit;

use Throwable;
use websvc\audit\behaviors\AuditBehavior;
use websvc\audit\components\ChangeBuilder;
use websvc\audit\components\ParamsMasker;
use websvc\audit\models\AuditChange;
use websvc\audit\models\AuditRequest;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\console\Request as ConsoleRequest;
use yii\console\Response as ConsoleResponse;
use yii\db\AfterSaveEvent;
use yii\db\BaseActiveRecord;
use yii\db\Connection;
use yii\di\Instance;
use yii\i18n\PhpMessageSource;
use yii\web\Application as WebApplication;
use yii\web\IdentityInterface;
use yii\web\Response;
use yii\web\User;

/**
 * Records ActiveRecord changes and write requests.
 *
 * Change rows are written through the model's own connection with database-qualified table names, so they belong to
 * the same transaction as the change they describe.
 */
class Audit extends Component implements BootstrapInterface
{
    public const TABLE_CHANGES = 'audit_changes';
    public const TABLE_REQUESTS = 'audit_requests';

    public const ACTION_INSERT = 'insert';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    /**
     * Method stored for console runs.
     */
    public const METHOD_CLI = 'CLI';

    private const MODEL_EVENTS = [
        BaseActiveRecord::EVENT_AFTER_INSERT,
        BaseActiveRecord::EVENT_AFTER_UPDATE,
        BaseActiveRecord::EVENT_AFTER_DELETE,
    ];

    /**
     * Application component id used by models, migrations and behaviors to find the component.
     */
    public static string $componentId = 'audit';

    /**
     * Connection used for request rows, the grids and cleanup.
     */
    public string|array|Connection $db = 'db';

    /**
     * Name of the audit database on the same server. Null keeps the tables in the connection's own database.
     */
    public ?string $database = null;

    /**
     * Track every ActiveRecord model through class-level events.
     */
    public bool $trackAllModels = false;

    /**
     * Model classes (and their subclasses) skipped when every model is tracked.
     *
     * @var string[]
     */
    public array $excludeModels = [];

    /**
     * Attributes never recorded, for every model.
     *
     * @var string[]
     */
    public array $ignoreAttributes = [];

    /**
     * Attributes recorded as changed with their values replaced by a mask.
     *
     * @var string[]
     */
    public array $maskAttributes = [
        'password',
        'password_hash',
        'auth_key',
        'password_reset_token',
        'verification_token',
        'access_token',
    ];

    /**
     * Request methods that always get a request row. Add `CLI` to record every console run.
     *
     * @var string[]
     */
    public array $trackMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes that never get a request row. `*` matches any characters.
     *
     * @var string[]
     */
    public array $ignoreRoutes = ['debug/*', 'gii/*'];

    /**
     * Parameter keys whose values are masked (case-insensitive substring match, at any depth).
     *
     * @var string[]
     */
    public array $maskParams = ['password', 'token', 'secret', 'csrf', 'auth_key', 'api_key'];

    /**
     * Parameter payloads larger than this (as JSON) are replaced by a truncation marker.
     */
    public int $maxParamsBytes = 65536;

    /**
     * `callable(\yii\web\IdentityInterface $identity): ?string` returning the name stored with each row.
     * Defaults to the identity's `username` property when it has one.
     *
     * @var callable|null
     */
    public $userNameCallback = null;

    /**
     * `callable(): ?string` returning the client IP. Defaults to `Request::getUserIP()`.
     *
     * @var callable|null
     */
    public $ipCallback = null;

    /**
     * Catch audit write errors and log them instead of breaking the request.
     */
    public bool $failSilently = true;

    private ?string $requestId = null;

    private int $changeCount = 0;

    private bool $requestRecorded = false;

    private ?float $startedAt = null;

    public function init(): void
    {
        parent::init();
        Yii::setAlias('@websvc/audit', __DIR__);
        $this->registerTranslations();
    }

    /**
     * Registers the `audit` translation category unless the application already configured it.
     */
    protected function registerTranslations(): void
    {
        if (Yii::$app === null) {
            return;
        }
        $i18n = Yii::$app->getI18n();
        if (isset($i18n->translations['audit']) || isset($i18n->translations['audit*'])) {
            return;
        }
        $i18n->translations['audit'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en-US',
            'basePath' => '@websvc/audit/messages',
        ];
    }

    /**
     * Returns the configured component, or a default instance when the application has none.
     */
    public static function getInstance(): self
    {
        if (Yii::$app !== null && Yii::$app->has(static::$componentId)) {
            $component = Yii::$app->get(static::$componentId);
            if ($component instanceof self) {
                return $component;
            }
        }

        return new self();
    }

    public function bootstrap($app): void
    {
        $this->startedAt = microtime(true);

        if ($this->trackAllModels) {
            foreach (self::MODEL_EVENTS as $name) {
                Event::on(BaseActiveRecord::class, $name, [$this, 'handleModelEvent']);
            }
        }

        if ($app instanceof WebApplication) {
            // Also fires when the error handler sends an error page
            $app->getResponse()->on(Response::EVENT_AFTER_SEND, [$this, 'recordRequest']);
        } else {
            $app->on(Application::EVENT_AFTER_REQUEST, [$this, 'recordRequest']);
        }
        // Covers requests that end with exit() before the events above
        register_shutdown_function([$this, 'recordRequest']);
    }

    /**
     * Writes the request row, at most once per request. Read-only requests that changed nothing are skipped.
     */
    public function recordRequest(?Event $event = null): void
    {
        $app = Yii::$app;
        if ($this->requestRecorded || $app === null) {
            return;
        }

        $isWeb = $app instanceof WebApplication;
        $method = $isWeb ? strtoupper($app->getRequest()->getMethod()) : self::METHOD_CLI;
        $route = (string) $app->requestedRoute;
        if ($this->isIgnoredRoute($route)) {
            return;
        }
        if ($this->changeCount === 0 && !in_array($method, array_map('strtoupper', $this->trackMethods), true)) {
            return;
        }
        $this->requestRecorded = true;

        try {
            [$userId, $userName] = $this->currentUser();
            $masker = new ParamsMasker($this->maskParams, $this->maxParamsBytes);
            $row = [
                'request_id' => $this->getRequestId(),
                'created_at' => gmdate('Y-m-d H:i:s', (int) ($this->startedAt ?? time())),
                'app_id' => $this->appId(),
                'user_id' => $userId,
                'user_name' => $userName,
                'method' => $method,
                'route' => mb_substr($route, 0, 255),
                'duration_ms' => $this->startedAt === null
                    ? null
                    : (int) round((microtime(true) - $this->startedAt) * 1000),
            ];
            $row += $isWeb ? $this->webRequestData($app, $masker) : $this->consoleRequestData($app, $masker);

            $this->insertRow($this->getDb(), self::TABLE_REQUESTS, $row);
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    public function isRequestRecorded(): bool
    {
        return $this->requestRecorded;
    }

    /**
     * Class-level handler used when every model is tracked.
     */
    public function handleModelEvent(Event $event): void
    {
        $model = $event->sender;
        if (!$model instanceof BaseActiveRecord || $this->isSkippedModel($model)) {
            return;
        }

        $changedAttributes = $event instanceof AfterSaveEvent ? $event->changedAttributes : [];
        if ($event->name === BaseActiveRecord::EVENT_AFTER_INSERT) {
            $this->recordChange($model, self::ACTION_INSERT, $changedAttributes);
        } elseif ($event->name === BaseActiveRecord::EVENT_AFTER_UPDATE) {
            if ($changedAttributes !== []) {
                $this->recordChange($model, self::ACTION_UPDATE, $changedAttributes);
            }
        } elseif ($event->name === BaseActiveRecord::EVENT_AFTER_DELETE) {
            $this->recordChange($model, self::ACTION_DELETE, []);
        }
    }

    public function getDb(): Connection
    {
        $this->db = Instance::ensure($this->db, Connection::class);

        return $this->db;
    }

    public function tableName(string $table): string
    {
        if ($this->database === null || $this->database === '') {
            return $table;
        }

        return $this->database . '.' . $table;
    }

    public function getRequestId(): string
    {
        if ($this->requestId === null) {
            $this->requestId = bin2hex(random_bytes(16));
        }

        return $this->requestId;
    }

    /**
     * Number of change rows written during this request.
     */
    public function getChangeCount(): int
    {
        return $this->changeCount;
    }

    /**
     * Writes one change row through the model's own connection, so the row shares the model's transaction.
     *
     * @param array $changedAttributes old values of the changed attributes, as given by `afterSave()`
     * @param string[] $ignoreAttributes added to the component's `ignoreAttributes`
     * @param string[] $maskAttributes added to the component's `maskAttributes`
     */
    public function recordChange(
        BaseActiveRecord $model,
        string $action,
        array $changedAttributes,
        array $ignoreAttributes = [],
        array $maskAttributes = []
    ): void {
        $db = $model::getDb();
        if (!$db instanceof Connection) {
            return;
        }

        try {
            $builder = new ChangeBuilder(
                array_merge($this->ignoreAttributes, $ignoreAttributes),
                array_merge($this->maskAttributes, $maskAttributes)
            );
            $attributes = $model->getAttributes();
            if ($action === self::ACTION_INSERT) {
                $changes = $builder->build($action, [], $attributes);
            } elseif ($action === self::ACTION_DELETE) {
                $changes = $builder->build($action, $attributes, []);
            } else {
                $changes = $builder->build(
                    $action,
                    $changedAttributes,
                    array_intersect_key($attributes, $changedAttributes)
                );
            }
            if ($changes === null) {
                return;
            }

            [$userId, $userName] = $this->currentUser();
            $this->insertRow($db, self::TABLE_CHANGES, [
                'request_id' => $this->getRequestId(),
                'created_at' => gmdate('Y-m-d H:i:s'),
                'app_id' => $this->appId(),
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => $action,
                'model_class' => get_class($model),
                'table_name' => $db->getSchema()->getRawTableName($model::tableName()),
                'record_id' => $this->recordId($model),
                'changes' => $this->encodeJson($changes),
            ]);
            $this->changeCount++;
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /**
     * Inserts with plain SQL, so no table schema has to be loaded.
     */
    protected function insertRow(Connection $db, string $table, array $row): void
    {
        $columns = [];
        $params = [];
        foreach ($row as $name => $value) {
            $columns[] = $db->quoteColumnName($name);
            $params[':' . $name] = $value;
        }

        $sql = 'INSERT INTO ' . $db->quoteTableName($this->tableName($table))
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_keys($params)) . ')';
        $db->createCommand($sql, $params)->execute();
    }

    protected function encodeJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    protected function handleError(Throwable $e): void
    {
        if (!$this->failSilently) {
            throw $e;
        }
        Yii::error('Audit write failed: ' . get_class($e) . ': ' . $e->getMessage(), 'audit');
    }

    protected function appId(): string
    {
        return Yii::$app !== null ? mb_substr((string) Yii::$app->id, 0, 64) : '';
    }

    /**
     * @return array{0: ?string, 1: ?string} user id and user name of the logged-in user
     */
    protected function currentUser(): array
    {
        $app = Yii::$app;
        if ($app === null || !$app->has('user')) {
            return [null, null];
        }
        $user = $app->get('user', false);
        if (!$user instanceof User) {
            return [null, null];
        }

        // Only web requests may load the identity from the session or cookie here
        $identity = $user->getIdentity($app instanceof WebApplication);
        if ($identity === null) {
            return [null, null];
        }

        $id = $identity->getId();
        $name = $this->userName($identity);

        return [
            $id === null ? null : mb_substr((string) $id, 0, 64),
            $name === null ? null : mb_substr($name, 0, 255),
        ];
    }

    private function userName(IdentityInterface $identity): ?string
    {
        if ($this->userNameCallback !== null) {
            $name = call_user_func($this->userNameCallback, $identity);

            return $name === null ? null : (string) $name;
        }

        return isset($identity->username) ? (string) $identity->username : null;
    }

    private function webRequestData(WebApplication $app, ParamsMasker $masker): array
    {
        $request = $app->getRequest();
        try {
            $body = (array) $request->getBodyParams();
        } catch (Throwable $e) {
            $body = [];
        }
        $params = [
            'get' => $masker->mask($request->getQueryParams()),
            'post' => $masker->mask($body),
            'files' => ParamsMasker::describeFiles($_FILES),
        ];
        try {
            $url = (string) $request->getUrl();
        } catch (Throwable $e) {
            // Thrown when the server gives no request URI; the row is still worth writing
            $url = '';
        }
        // The query string is left out: query parameters are stored masked in params
        $path = explode('?', $url, 2)[0];
        $ip = $this->ipCallback !== null ? call_user_func($this->ipCallback) : $request->getUserIP();
        $userAgent = $request->getUserAgent();

        return [
            'url' => mb_substr($path, 0, 2048),
            'params' => $masker->encode($params),
            'status_code' => $app->getResponse()->statusCode,
            'ip' => $ip === null ? null : mb_substr((string) $ip, 0, 45),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ];
    }

    private function consoleRequestData(Application $app, ParamsMasker $masker): array
    {
        $request = $app->getRequest();
        $args = $request instanceof ConsoleRequest ? $request->getParams() : [];
        $response = $app->getResponse();

        return [
            'url' => '',
            'params' => $masker->encode(['args' => $masker->maskArgs($args)]),
            'status_code' => $response instanceof ConsoleResponse ? $response->exitStatus : null,
            'ip' => null,
            'user_agent' => null,
        ];
    }

    private function isIgnoredRoute(string $route): bool
    {
        foreach ($this->ignoreRoutes as $pattern) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            if (preg_match($regex, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Models skipped in global mode: the package's own models, excluded classes, and models that carry an
     * AuditBehavior (the behavior records them).
     */
    private function isSkippedModel(BaseActiveRecord $model): bool
    {
        if ($model instanceof AuditChange || $model instanceof AuditRequest) {
            return true;
        }
        foreach ($this->excludeModels as $class) {
            if (is_a($model, $class)) {
                return true;
            }
        }
        foreach ($model->getBehaviors() as $behavior) {
            if ($behavior instanceof AuditBehavior) {
                return true;
            }
        }

        return false;
    }

    private function recordId(BaseActiveRecord $model): ?string
    {
        try {
            $keys = $model::primaryKey();
        } catch (Throwable $e) {
            return null;
        }
        if ($keys === []) {
            return null;
        }

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $model->getAttribute($key);
        }
        if (count($values) === 1) {
            $value = reset($values);

            return $value === null ? null : mb_substr((string) $value, 0, 255);
        }

        return mb_substr($this->encodeJson($values), 0, 255);
    }
}
