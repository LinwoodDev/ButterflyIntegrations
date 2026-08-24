<?php

declare(strict_types=1);

namespace Service;

use OCA\Butterfly\Service\BundleValidationException;
use OCA\Butterfly\Service\BundleValidator;
use PHPUnit\Framework\TestCase;

final class BundleValidatorTest extends TestCase {
	/** @var list<string> */
	private array $temporaryFiles = [];

	protected function tearDown(): void {
		foreach ($this->temporaryFiles as $file) {
			@unlink($file);
		}
	}

	public function testAcceptsMinimumStringBuildNumberInNestedWebRoot(): void {
		$archive = $this->createArchive([
			'butterfly/index.html' => '<html><base href="/"></html>',
			'butterfly/version.json' => '{"package_name":"butterfly","build_number":"193"}',
			'butterfly/main.dart.js' => 'console.log("Butterfly");',
		]);

		$this->assertSame([
			'root' => 'butterfly/',
			'packageName' => 'butterfly',
			'buildNumber' => '193',
		], (new BundleValidator())->validate($archive));
		$archive->close();
	}

	/**
	 * @dataProvider invalidVersionProvider
	 */
	public function testRejectsInvalidVersionMetadata(string $version, string $message): void {
		$archive = $this->createArchive([
			'index.html' => '<html></html>',
			'version.json' => $version,
		]);

		$this->expectException(BundleValidationException::class);
		$this->expectExceptionMessage($message);
		try {
			(new BundleValidator())->validate($archive);
		} finally {
			$archive->close();
		}
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function invalidVersionProvider(): iterable {
		yield 'wrong package' => [
			'{"package_name":"other","build_number":"193"}',
			'package_name',
		];
		yield 'numeric build number' => [
			'{"package_name":"butterfly","build_number":193}',
			'string build_number',
		];
		yield 'old build' => [
			'{"package_name":"butterfly","build_number":"192"}',
			'"193" or higher',
		];
	}

	public function testRejectsUnsafeArchivePath(): void {
		$this->expectException(BundleValidationException::class);
		(new BundleValidator())->normalizeEntryName('../version.json');
	}

	/**
	 * @param array<string, string> $files
	 */
	private function createArchive(array $files): \ZipArchive {
		$path = tempnam(sys_get_temp_dir(), 'butterfly-test-');
		if ($path === false) {
			self::fail('Could not create a temporary ZIP path.');
		}
		$this->temporaryFiles[] = $path;

		$archive = new \ZipArchive();
		self::assertTrue($archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
		foreach ($files as $name => $content) {
			self::assertTrue($archive->addFromString($name, $content));
		}
		self::assertTrue($archive->close());
		self::assertTrue($archive->open($path, \ZipArchive::RDONLY));

		return $archive;
	}
}
