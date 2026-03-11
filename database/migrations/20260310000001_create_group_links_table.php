<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGroupLinksTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('group_links');
        $table
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('title', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('url', 'string', ['limit' => 2048, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id'])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
