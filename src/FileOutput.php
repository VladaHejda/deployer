<?php declare(strict_types = 1);

namespace Deployer;

use function fclose;
use function fopen;
use function fwrite;
use function sprintf;

class FileOutput implements Output
{

	private $fileHandler;

	public function __construct(string $file)
	{
		$this->fileHandler = fopen($file, 'wb');
	}

	public function message(string $message): void
	{
		fwrite($this->fileHandler, sprintf("%s\n", $message));
	}

	public function __destruct()
	{
		fclose($this->fileHandler);
	}

}
