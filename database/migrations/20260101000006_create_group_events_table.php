<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGroupEventsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('group_events');
        $table
            ->addColumn('group_id', 'integer', ['null' => false])
            ->addColumn('creator_id', 'integer', ['null' => false])
            ->addColumn('title', 'string', ['null' => false])
            ->addColumn('description', 'text', ['null' => false, 'default' => ''])
            ->addColumn('event_date', 'string', ['null' => false])
            ->addColumn('event_time', 'string', ['null' => false])
            ->addColumn('location', 'string', ['null' => false, 'default' => ''])
            ->addColumn('meeting_url', 'string', ['null' => false, 'default' => ''])
            ->addColumn('created_at', 'string', ['null' => false, 'default' => 'datetime(\'now\')'])
            ->addIndex(['group_id'])
            ->addIndex(['event_date'])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('creator_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
