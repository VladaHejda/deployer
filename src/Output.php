<?php declare(strict_types = 1);

namespace Deployer;

interface Output
{

	public function message(string $message): void;

}
