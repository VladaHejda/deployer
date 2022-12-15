<?php declare(strict_types = 1);

namespace Deployer;

use function sprintf;

class FtpRemoteHandlerException extends RemoteHandlerException
{

	public function __construct(string $message)
	{
		$error = error_get_last();
		if ($error !== null) {
			$message = sprintf('%s %s', $message, $error['message']);
		}

		parent::__construct($message);
	}

}
