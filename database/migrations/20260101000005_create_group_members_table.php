<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGroupMembersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('group_members');
        $table
            ->addColumn('group_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('joined_at', 'string', ['null' => false, 'default' => 'datetime(\'now\')'])
            ->addIndex(['group_id', 'user_id'], ['unique' => true])
            ->addIndex(['group_id'])
            ->addIndex(['user_id'])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
