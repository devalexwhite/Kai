<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGroupMembersTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('group_members');
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('user_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('joined_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id', 'user_id'], ['unique' => true])
            ->addIndex(['group_id'])
            ->addIndex(['user_id'])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
