<?php
declare(strict_types = 1);

namespace salmonde\crashlogger;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use salmonde\crashlogger\utils\TestException;

class CrashTester implements CommandExecutor {

	private int $code;

	public function __construct(){
		$this->code = random_int(1000, 9999);
	}

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
		if(ctype_digit($args[0] ?? "") and $this->code === (int) $args[0]){
			throw new TestException("Crashing server deliberately");
		}

		$sender->sendMessage(TextFormat::RED."Caution! This command will crash the server if used appropriately. To intentionally crash the server, run this command with this code: /".$cmd->getName()." ".TextFormat::AQUA.$this->code);
		return true;
	}
}
