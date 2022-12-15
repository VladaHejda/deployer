<?php declare(strict_types = 1);

namespace Deployer;

class CompoundOutput implements Output
{

    /** @var Output[] */
    private array $outputs;

    public function addOutput(Output $output): void
    {
        $this->outputs[] = $output;
    }

	public function message(string $message): void
	{
        foreach ($this->outputs as $output) {
	        $output->message($message);
        }
	}

}
