<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRememberTokensTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('remember_tokens');
        $table
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('selector', 'string', ['null' => false])
            ->addColumn('token_hash', 'string', ['null' => false])
            ->addColumn('expires_at', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['selector'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}
