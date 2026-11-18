<?php

namespace websvc\audit;

use Yii;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\db\Connection;
use yii\di\Instance;

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

    public function init(): void
    {
        parent::init();
        Yii::setAlias('@websvc/audit', __DIR__);
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
}
