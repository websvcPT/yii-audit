<?php

namespace websvc\audit\behaviors;

use websvc\audit\Audit;
use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\AfterSaveEvent;
use yii\db\BaseActiveRecord;

/**
 * Records the owner's inserts, updates and deletes through the `audit` component.
 *
 * @property BaseActiveRecord $owner
 */
class AuditBehavior extends Behavior
{
    /**
     * The audit component, or its application component id.
     */
    public string|array|Audit $component = 'audit';

    /**
     * Attributes never recorded for this model, added to the component's list.
     *
     * @var string[]
     */
    public array $ignoreAttributes = [];

    /**
     * Attributes masked for this model, added to the component's list.
     *
     * @var string[]
     */
    public array $maskAttributes = [];

    public function events(): array
    {
        return [
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            BaseActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function afterInsert(AfterSaveEvent $event): void
    {
        $this->record(Audit::ACTION_INSERT, $event->changedAttributes);
    }

    public function afterUpdate(AfterSaveEvent $event): void
    {
        if ($event->changedAttributes === []) {
            return;
        }
        $this->record(Audit::ACTION_UPDATE, $event->changedAttributes);
    }

    public function afterDelete(Event $event): void
    {
        $this->record(Audit::ACTION_DELETE, []);
    }

    /**
     * Returns the audit component, or null when the application has none.
     */
    public function getAudit(): ?Audit
    {
        if ($this->component instanceof Audit) {
            return $this->component;
        }
        if (is_array($this->component)) {
            $this->component = Yii::createObject($this->component);

            return $this->component;
        }
        if (Yii::$app === null || !Yii::$app->has($this->component)) {
            return null;
        }
        $component = Yii::$app->get($this->component);

        return $component instanceof Audit ? $component : null;
    }

    private function record(string $action, array $changedAttributes): void
    {
        $audit = $this->getAudit();
        if ($audit === null) {
            return;
        }
        $audit->recordChange($this->owner, $action, $changedAttributes, $this->ignoreAttributes, $this->maskAttributes);
    }
}
