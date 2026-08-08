<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentVersionModel;
use App\Services\FileStorageService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * Guards the upload whitelist in {@see FileStorageService::store()}.
 *
 * The point of these cases is that the decision to accept a file is made from
 * the file's own bytes (finfo), not from the Content-Type header and filename
 * the browser supplied -- both of which an attacker writes freely.
 *
 * @internal
 */
final class FileStorageServiceTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    /** Upload directory created by the accept-path test. */
    private ?string $storedDir = null;

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->tempFiles = [];

        if ($this->storedDir !== null && is_dir($this->storedDir)) {
            foreach ((array) glob($this->storedDir . '/*') as $stored) {
                unlink((string) $stored);
            }

            rmdir($this->storedDir);
            $this->storedDir = null;
        }

        parent::tearDown();
    }

    /**
     * Writes $contents to a temporary file and returns its path.
     */
    private function tempFile(string $contents, string $extension): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'promotrack_test_' . bin2hex(random_bytes(6)) . '.' . $extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Builds the service with a DocumentVersionModel that fails the test if it
     * is touched: every case here must be refused before any row is written.
     */
    private function serviceThatMustNotWrite(): FileStorageService
    {
        $model = $this->createMock(DocumentVersionModel::class);
        $model->expects($this->never())->method('insert');

        return new FileStorageService($model);
    }

    public function testAPhpScriptRenamedToPdfIsRejectedDespiteAPdfContentTypeHeader(): void
    {
        $path = $this->tempFile("<?php system(\$_GET['c']); ?>\n", 'pdf');

        // Exactly what a crafted multipart request can claim.
        $file = new FakeUploadedFile($path, 'payroll.pdf', 'application/pdf', filesize($path), UPLOAD_ERR_OK);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only PDF, JPG, and PNG files are accepted.');

        $this->serviceThatMustNotWrite()->store($file, 1, 1);
    }

    public function testAPlainTextFileClaimingToBeAPngIsRejected(): void
    {
        $path = $this->tempFile("not an image at all\n", 'png');
        $file = new FakeUploadedFile($path, 'scan.png', 'image/png', filesize($path), UPLOAD_ERR_OK);

        $this->expectException(RuntimeException::class);

        $this->serviceThatMustNotWrite()->store($file, 1, 1);
    }

    public function testAGenuinePngIsNotRejectedByTheWhitelist(): void
    {
        // Smallest valid PNG: 1x1 transparent pixel.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true
        );

        $path = $this->tempFile($png, 'png');
        $file = new FakeUploadedFile($path, 'photo.png', 'image/png', filesize($path), UPLOAD_ERR_OK);

        $model = $this->createMock(DocumentVersionModel::class);
        $model->method('nextVersionNumber')->willReturn(1);
        $model->method('getInsertID')->willReturn(7);
        $model->method('find')->willReturn(['id' => 7, 'version_number' => 1]);

        $stored = (new FileStorageService($model))->store($file, 999, 1);

        $this->assertSame(7, $stored['id']);
        $this->storedDir = WRITEPATH . 'uploads/package_documents/999';
    }

    public function testAFileOverTheSizeLimitIsRejected(): void
    {
        $path = $this->tempFile('x', 'pdf');
        $file = new FakeUploadedFile($path, 'huge.pdf', 'application/pdf', 11 * 1024 * 1024, UPLOAD_ERR_OK);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the 10 MB size limit');

        $this->serviceThatMustNotWrite()->store($file, 1, 1);
    }
}

/**
 * UploadedFile relies on is_uploaded_file(), which is only ever true for a real
 * multipart request. This double reports the file as a valid upload and copies
 * on move() instead of move_uploaded_file(); nothing else is changed, so
 * getMimeType()/guessExtension() still inspect the real bytes.
 */
final class FakeUploadedFile extends UploadedFile
{
    public function isValid(): bool
    {
        return true;
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $name ??= $this->getName();
        copy($this->getTempName(), rtrim($targetPath, '/\\') . DIRECTORY_SEPARATOR . $name);

        return true;
    }
}
