<?php declare(strict_types = 1);

namespace Deployer;

use function flush;

class EchoOutput implements Output
{

	public function message(string $message): void
	{
		echo sprintf("%s\n", $message);
		flush();
	}

}
