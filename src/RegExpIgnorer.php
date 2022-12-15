<?php declare(strict_types = 1);

namespace Deployer;

use function preg_match;

class RegExpIgnorer implements Ignorer
{

	/** @var string[] */
	private array $regExps = [];

	public function isIgnored(string $item): bool
	{
		foreach ($this->regExps as $regExp) {
			$match = preg_match($regExp, $item);
			if ($match !== false && $match > 0) {
				return true;
			}
		}

		return false;
	}

	public function addRegExp(string $regExp): void
	{
		$this->regExps[] = $regExp;
	}

}
