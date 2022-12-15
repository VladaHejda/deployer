<?php declare(strict_types = 1);

namespace Deployer;

class NullOutput implements Output
{

	public function message(string $message): void
	{
		// black hole
	}

}