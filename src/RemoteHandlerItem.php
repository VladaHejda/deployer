<?php declare(strict_types = 1);

namespace Deployer;

class RemoteHandlerItem
{

	public string $name;
	public string $path;
	public bool $isDir;

	public function __construct(
		string $name,
		string $path,
		bool $isDir
	)
	{
		$this->name = $name;
		$this->path = $path;
		$this->isDir = $isDir;
	}

}
