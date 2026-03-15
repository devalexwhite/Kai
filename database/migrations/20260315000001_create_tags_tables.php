<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTagsTables extends AbstractMigration
{
    public function change(): void
    {
        $tags = $this->table('tags');
        $tags
            ->addColumn('name', 'string', ['limit' => 30, 'null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $groupTags = $this->table('group_tags');
        $groupTags
            ->addColumn('group_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('tag_id', 'integer', ['null' => false, 'signed' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['group_id', 'tag_id'], ['unique' => true])
            ->addIndex(['tag_id'])
            ->addForeignKey('group_id', 'user_groups', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
