<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\ModuleTemplate;

use Gacela\Console\Domain\CommandArguments\CommandArguments;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;

final class ModuleTemplateGenerator
{
    /**
     * @return list<string>
     */
    public function generateTemplateFiles(
        CommandArguments $arguments,
        string $template,
        bool $withTests,
        bool $withApi,
    ): array {
        $generatedFiles = [];

        match ($template) {
            'crud' => $generatedFiles = $this->generateCrudTemplate($arguments),
            'api' => $generatedFiles = $this->generateApiTemplate($arguments),
            'cli' => $generatedFiles = $this->generateCliTemplate($arguments),
            default => [],
        };

        if ($withTests) {
            $generatedFiles = array_merge($generatedFiles, $this->generateTestFiles($arguments));
        }

        if ($withApi) {
            return array_merge($generatedFiles, $this->generateApiController($arguments));
        }

        return $generatedFiles;
    }

    /**
     * @return list<string>
     */
    private function generateCrudTemplate(CommandArguments $arguments): array
    {
        $files = [];
        $namespace = $arguments->namespace();
        $directory = $arguments->directory();

        // Generate Repository
        $repositoryPath = $directory . '/Repository/Repository.php';
        $this->ensureDirectoryExists($repositoryPath);
        $repositoryContent = $this->getRepositoryTemplate($namespace);
        file_put_contents($repositoryPath, $repositoryContent);
        $files[] = $repositoryPath;

        // Generate Entity
        $entityPath = $directory . '/Domain/Entity.php';
        $this->ensureDirectoryExists($entityPath);
        $entityContent = $this->getEntityTemplate($namespace);
        file_put_contents($entityPath, $entityContent);
        $files[] = $entityPath;

        return $files;
    }

    /**
     * @return list<string>
     */
    private function generateApiTemplate(CommandArguments $arguments): array
    {
        $files = [];
        $namespace = $arguments->namespace();
        $directory = $arguments->directory();

        // Generate API Controller
        $controllerPath = $directory . '/Presentation/ApiController.php';
        $this->ensureDirectoryExists($controllerPath);
        $controllerContent = $this->getApiControllerTemplate($namespace);
        file_put_contents($controllerPath, $controllerContent);
        $files[] = $controllerPath;

        return $files;
    }

    /**
     * @return list<string>
     */
    private function generateCliTemplate(CommandArguments $arguments): array
    {
        $files = [];
        $namespace = $arguments->namespace();
        $directory = $arguments->directory();

        // Generate CLI Command
        $commandPath = $directory . '/Presentation/Command.php';
        $this->ensureDirectoryExists($commandPath);
        $commandContent = $this->getCliCommandTemplate($namespace);
        file_put_contents($commandPath, $commandContent);
        $files[] = $commandPath;

        return $files;
    }

    /**
     * @return list<string>
     */
    private function generateTestFiles(CommandArguments $arguments): array
    {
        $files = [];
        $namespace = $arguments->namespace();
        $directory = $arguments->directory();
        $testDirectory = str_replace('/src/', '/tests/', $directory);

        // Generate Facade test
        $testPath = $testDirectory . '/FacadeTest.php';
        $this->ensureDirectoryExists($testPath);
        $testContent = $this->getFacadeTestTemplate($namespace);
        file_put_contents($testPath, $testContent);
        $files[] = $testPath;

        return $files;
    }

    /**
     * @return list<string>
     */
    private function generateApiController(CommandArguments $arguments): array
    {
        return $this->generateApiTemplate($arguments);
    }

    private function getRepositoryTemplate(string $namespace): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s\Repository;

final class Repository
{
    public function findById(int $id): ?array
    {
        // TODO: Implement repository logic
        return null;
    }

    public function save(array $data): int
    {
        // TODO: Implement save logic
        return 0;
    }

    public function delete(int $id): bool
    {
        // TODO: Implement delete logic
        return false;
    }
}

PHP,
            $namespace,
        );
    }

    private function getEntityTemplate(string $namespace): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s\Domain;

final class Entity
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}

PHP,
            $namespace,
        );
    }

    private function getApiControllerTemplate(string $namespace): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s\Presentation;

final class ApiController
{
    public function index(): array
    {
        // TODO: Implement index endpoint
        return ['data' => []];
    }

    public function show(int $id): array
    {
        // TODO: Implement show endpoint
        return ['data' => ['id' => $id]];
    }

    public function store(array $request): array
    {
        // TODO: Implement store endpoint
        return ['data' => [], 'message' => 'Created'];
    }

    public function update(int $id, array $request): array
    {
        // TODO: Implement update endpoint
        return ['data' => ['id' => $id], 'message' => 'Updated'];
    }

    public function destroy(int $id): array
    {
        // TODO: Implement destroy endpoint
        return ['message' => 'Deleted'];
    }
}

PHP,
            $namespace,
        );
    }

    private function getCliCommandTemplate(string $namespace): string
    {
        return sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace %s\Presentation;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Command extends \Symfony\Component\Console\Command\Command
{
    protected function configure(): void
    {
        $this->setName('module:command')
            ->setDescription('Module CLI command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello from module command!');

        return self::SUCCESS;
    }
}

PHP,
            $namespace,
        );
    }

    private function getFacadeTestTemplate(string $namespace): string
    {
        $facadeClass = $namespace . '\\Facade';

        return sprintf(
            <<<'PHP_WRAP'
<?php

declare(strict_types=1);

namespace %sTest;

use PHPUnit\Framework\TestCase;
use %s;

final class FacadeTest extends TestCase
{
    public function testFacadeInstance(): void
    {
        $facade = new Facade();

        $this->assertInstanceOf(Facade::class, $facade);
    }
}

PHP_WRAP
            ,
            $namespace,
            $facadeClass,
        );
    }

    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
