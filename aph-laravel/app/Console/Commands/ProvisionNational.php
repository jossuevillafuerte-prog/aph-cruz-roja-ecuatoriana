<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionNational extends Command
{
    protected $signature = 'aph:provision-national {credentialFile}';

    protected $description = 'Crea la cuenta nacional confirmada y guarda su clave inicial en un archivo local privado.';

    public function handle(): int
    {
        if (DB::table('accounts')->where('username', 'manejoinformacion')->exists()) {
            $this->info('La cuenta ya existe. No se modificó su contraseña.');

            return 0;
        }
        $path = $this->argument('credentialFile');
        if (file_exists($path) || ! is_dir(dirname($path))) {
            $this->error('Usa un archivo nuevo dentro de un directorio privado existente.');

            return 1;
        }
        $password = bin2hex(random_bytes(10)).'A7!';
        DB::transaction(function () use ($password, $path) {
            DB::table('accounts')->insert(['username' => 'manejoinformacion', 'name' => 'Cuenta de Manejo de la Información', 'email' => 'manejoinformacion@cruzroja.org.ec', 'role' => 'superadmin', 'password' => password_hash($password, PASSWORD_BCRYPT), 'active' => true, 'legacy_access' => true, 'created_at' => now(), 'updated_at' => now()]);
            $content = "Superadministrador nacional APH\nUsuario: manejoinformacion\nCorreo: manejoinformacion@cruzroja.org.ec\nContraseña inicial: $password\nCambiar la contraseña desde Mi perfil después del primer ingreso.\nCorreo pendiente de verificación por excepción temporal autorizada.\n";
            if (file_put_contents($path, $content, LOCK_EX) === false) {
                throw new \RuntimeException('No se pudo guardar la credencial.');
            }
        });
        $this->info('Cuenta nacional creada. Credencial guardada en el archivo indicado; no se muestra en consola.');

        return 0;
    }
}
