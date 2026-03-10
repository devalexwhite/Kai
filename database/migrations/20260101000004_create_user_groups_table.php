<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserGroupsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_groups');
        $table
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('description', 'text', ['null' => false, 'default' => ''])
            ->addColumn('city_id', 'integer', ['null' => false])
            ->addColumn('creator_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'string', ['null' => false, 'default' => 'datetime(\'now\')'])
            ->addIndex(['city_id'])
            ->addIndex(['creator_id'])
            ->addForeignKey('city_id', 'cities', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
            ->addForeignKey('creator_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
