<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use Tests\TestCase;

class InitialSchemaTest extends TestCase
{
    public function test_mysql_migrations_create_and_drop_tables_in_dependency_order(): void
    {
        $created = [];
        $references = [];

        foreach ($this->migrationQueries('up') as $query) {
            if (preg_match('/^create table `([^`]+)`/', $query, $matches)) {
                $this->assertNotContains($matches[1], $created);
                $created[] = $matches[1];
            }

            if (preg_match('/^alter table `([^`]+)`.*references `([^`]+)`/', $query, $matches)) {
                $this->assertContains($matches[1], $created);
                $this->assertContains($matches[2], $created);
                $references[$matches[1]][] = $matches[2];
            }
        }

        $this->assertCount(27, $created);
        $this->assertContains('usuarios', $created);
        $this->assertContains('endereco_entrega_pedido', $created);
        $this->assertNotContains('users', $created);

        $remaining = $created;

        foreach ($this->migrationQueries('down') as $query) {
            $this->assertMatchesRegularExpression('/^drop table if exists `([^`]+)`$/', $query);
            preg_match('/^drop table if exists `([^`]+)`$/', $query, $matches);
            $table = $matches[1];
            $this->assertContains($table, $remaining);

            foreach ($remaining as $child) {
                if ($child !== $table) {
                    $this->assertNotContains($table, $references[$child] ?? [], "{$child} must be dropped before {$table}");
                }
            }

            $remaining = array_values(array_diff($remaining, [$table]));
        }

        $this->assertSame([], $remaining);
    }

    public function test_mysql_schema_preserves_order_snapshots_and_one_to_one_details(): void
    {
        $sql = implode("\n", $this->migrationQueries('up'));

        foreach (['perifericos', 'processadores', 'memorias_ram', 'placas_video', 'placas_mae', 'armazenamento', 'fontes', 'gabinetes', 'refrigeracao'] as $table) {
            $this->assertMatchesRegularExpression('/create table `'.$table.'` .*primary key \(`produto_id`\)/', $sql);
        }

        $this->assertStringContainsString('`categoria_nome_snapshot` varchar(100) not null', $sql);
        $this->assertStringContainsString('`nome_produto_snapshot` varchar(150) not null', $sql);
        $this->assertStringContainsString('`preco_unitario` decimal(10, 2) unsigned not null', $sql);
        $this->assertStringContainsString('foreign key (`produto_id`) references `produtos` (`id`) on delete set null', $sql);
        $this->assertStringContainsString('foreign key (`usuario_id`) references `usuarios` (`id`) on delete restrict', $sql);
    }

    public function test_user_uses_portuguese_schema_and_hashes_its_password(): void
    {
        $user = User::factory()->make(['senha_hash' => 'senha-de-teste']);

        $this->assertSame('usuarios', $user->getTable());
        $this->assertSame('criado_em', $user->getCreatedAtColumn());
        $this->assertNull($user->getUpdatedAtColumn());
        $this->assertSame('senha_hash', $user->getAuthPasswordName());
        $this->assertTrue(Hash::check('senha-de-teste', $user->getAuthPassword()));
        $this->assertFalse($user->isFillable('tipo'));
        $this->assertArrayNotHasKey('senha_hash', $user->toArray());
    }

    /** @return list<string> */
    private function migrationQueries(string $direction): array
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_SERVER_VERSION)->andReturn('8.0.36');
        $connection = new MySqlConnection($pdo, 'poczy_hardware', '', [
            'name' => 'migration-preview',
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'engine' => 'InnoDB',
        ]);

        $originalSchema = Schema::getFacadeRoot();
        Schema::swap($connection->getSchemaBuilder());

        try {
            $files = glob(database_path('migrations/*.php'));
            sort($files);

            if ($direction === 'down') {
                $files = array_reverse($files);
            }

            $queries = $connection->pretend(function () use ($files, $direction): void {
                foreach ($files as $file) {
                    $migration = require $file;
                    $migration->{$direction}();
                }
            });

            return array_column($queries, 'query');
        } finally {
            Schema::swap($originalSchema);
        }
    }
}
