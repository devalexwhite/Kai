<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSlugToUserGroups extends AbstractMigration
{
    public function up(): void
    {
        $this->table('user_groups')
            ->addColumn('slug', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->update();

        $groups = $this->fetchAll('SELECT id, name FROM user_groups ORDER BY id ASC');
        $used   = [];

        foreach ($groups as $group) {
            $slug = $this->slugify($group['name']);
            $base = $slug;
            $i    = 2;

            while (in_array($slug, $used, true)) {
                $slug = $base . '-' . $i++;
            }

            $used[] = $slug;
            $id     = (int) $group['id'];
            $this->execute("UPDATE user_groups SET slug = '{$slug}' WHERE id = {$id}");
        }

        $this->table('user_groups')
            ->addIndex(['slug'], ['unique' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('user_groups')
            ->removeIndex(['slug'])
            ->removeColumn('slug')
            ->update();
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'group';
    }
}
