<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallationWizardTest extends TestCase
{
    #[Test]
    public function first_install_page_renders_without_booting_laravel_configuration(): void
    {
        $html = $this->runInstallerRequest('/install');

        $this->assertStringContainsString('Server requirements', $html);
        $this->assertStringContainsString('PHP 8.3 or higher', $html);
        $this->assertStringContainsString('Database', $html);
        $this->assertStringContainsString('name="csrf"', $html);
        $this->assertStringNotContainsString('DB_PASSWORD', $html);
    }

    #[Test]
    public function installer_renders_a_progress_indicator_and_marks_required_checks(): void
    {
        $html = $this->runInstallerRequest('/install');

        $this->assertStringContainsString('Requirements', $html);
        $this->assertStringContainsString('Database', $html);
        $this->assertStringContainsString('Application', $html);
        $this->assertStringContainsString('Administrator', $html);
        $this->assertStringContainsString('Required', $html);
        $this->assertStringContainsString('Recommended', $html);
    }

    private function runInstallerRequest(string $path): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $frontController = var_export($projectRoot.'/public/index.php', true);
        $requestPath = var_export($path, true);
        $script = '$_SERVER["REQUEST_URI"]='.$requestPath.';'
            .'$_SERVER["SCRIPT_NAME"]="/index.php";'
            .'$_SERVER["REQUEST_METHOD"]="GET";'
            .'$_SERVER["HTTP_HOST"]="installer.test";'
            .'require '.$frontController.';';

        $process = proc_open([PHP_BINARY, '-r', $script], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $projectRoot);

        $this->assertIsResource($process, 'Unable to start the isolated installer request.');
        fclose($pipes[0]);
        $html = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $errors);
        return $html;
    }
}
