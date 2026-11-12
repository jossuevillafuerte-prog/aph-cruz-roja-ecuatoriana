<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportPilot extends Command
{
    protected $signature = 'aph:import {source}';

    protected $description = 'Importa SQLite a MySQL vacío sin modificar el origen.';

    public function handle(): int
    {
        $source = realpath($this->argument('source'));
        if (! $source || ! is_file($source)) {
            $this->error('No existe el origen.');

            return 1;
        }
        if (DB::table('usuarios')->exists() || DB::table('atenciones')->exists()) {
            $this->error('El destino ya tiene datos.');

            return 1;
        }
        $old = new PDO('sqlite:'.$source, options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $old->exec('PRAGMA query_only=ON');
        $tables = ['usuarios', 'operadores', 'perfiles', 'recursos', 'atenciones', 'cambios', 'geocodificacion'];
        DB::transaction(function () use ($old, $tables) {
            foreach ($tables as $table) {
                $rows = $old->query('SELECT * FROM '.$table)->fetchAll();
                foreach ($rows as $row) {
                    DB::table($table)->insert($row);
                }if (DB::table($table)->count() !== count($rows)) {
                    throw new \RuntimeException('Conteo incorrecto: '.$table);
                }$this->info($table.': '.count($rows));
            }
        });
        $this->info('Importación completada. Sesiones antiguas no importadas.');

        return 0;
    }
}
