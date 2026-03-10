<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCitiesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('cities');
        $table
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('state', 'string', ['null' => false])
            ->addIndex(['name', 'state'], ['unique' => true])
            ->create();
    }
}
