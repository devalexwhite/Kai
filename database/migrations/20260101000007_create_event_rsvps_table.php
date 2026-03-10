<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEventRsvpsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('event_rsvps');
        $table
            ->addColumn('event_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'string', ['null' => false, 'default' => 'datetime(\'now\')'])
            ->addIndex(['event_id', 'user_id'], ['unique' => true])
            ->addIndex(['event_id'])
            ->addIndex(['user_id'])
            ->addForeignKey('event_id', 'group_events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
