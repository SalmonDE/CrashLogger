<?php
declare(strict_types = 1);

namespace salmonde\crashlogger;

use InvalidArgumentException;
use pocketmine\plugin\PluginBase;
use salmonde\crashlogger\utils\CrashDumpReader;
use salmonde\crashlogger\utils\DiscordHandler;

class Main extends PluginBase {

	protected function onEnable(): void{
		$this->saveResource("config.yml");
		$this->checkOldCrashDumps();

		$this->getServer()->getCommandMap()->getCommand("crashloggertest")->setExecutor(new CrashTester());
	}

	protected function onDisable(): void{
		$this->checkNewCrashDump();
	}

	private function checkOldCrashDumps(): void{
		$validityDuration = $this->getConfig()->get("validity-duration", 24) * 60 * 60;
		$delete = $this->getConfig()->get("delete-files", false);

		$files = $this->getCrashdumpFiles();
		$this->getLogger()->info("Checking old crash dumps (files: ".count($files).")");

		$removed = 0;
		foreach($files as $filePath){
			try{
				$crashDumpReader = new CrashDumpReader($filePath);

				if(!$crashDumpReader->hasRead()){
					continue;
				}

				if($delete === true and time() - $crashDumpReader->getCreationTime() >= $validityDuration){
					unlink($filePath);
					++$removed;
				}
			}catch(\Throwable $e){
				$this->getLogger()->warning("Error during file check of \"".basename($filePath)."\": ".$e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine());
				foreach(explode("\n", $e->getTraceAsString()) as $traceString){
					$this->getLogger()->debug("[ERROR] ".$traceString);
				}
			}
		}

		$fileAmount = count($files);
		$percentage = $fileAmount > 0 ? round($removed * 100 / $fileAmount, 2) : "NAN";

		$message = "Checks finished, Deleted crash dump files: ".$removed." (".$percentage."%)";
		if($removed > 0){
			$this->getLogger()->notice($message);
		}else{
			$this->getLogger()->info($message);
		}
	}

	private function checkNewCrashDump(): void{
		if($this->getConfig()->get("report-crash", false) !== true){
			return;
		}

		if(trim($this->getConfig()->get("webhook-url", "")) === ""){
			throw new InvalidArgumentException("Webhook url is invalid");
		}

		$this->getLogger()->debug("Checking for new crash dump");
		$files = $this->getCrashdumpFiles();

		$startTime = (int) $this->getServer()->getStartTime();
		foreach($files as $filePath){
			try{
				$crashDumpReader = new CrashDumpReader($filePath);

				if(!$crashDumpReader->hasRead() or $crashDumpReader->getCreationTime() < $startTime){
					continue;
				}

				$this->getLogger()->notice("New crash dump found. Sending now.");
				$this->reportCrashDump($crashDumpReader);
			}catch(\Throwable $e){
				$this->getLogger()->warning("Error while checking potentially new crash dump \"".basename($filePath)."\": ".$e->getMessage()." in file ".$e->getFile()." on line ".$e->getLine());
				foreach(explode("\n", $e->getTraceAsString()) as $traceString){
					$this->getLogger()->debug("[ERROR] ".$traceString);
				}
			}
		}

		$this->getLogger()->debug("Checks finished");
	}

	private function reportCrashDump(CrashDumpReader $crashDumpReader): void{
		if($crashDumpReader->hasRead()){
			$handler = new DiscordHandler($this->getConfig()->get("webhook-url"), $crashDumpReader);
			$handler->announceCrash = $this->getConfig()->get("announce-crash-report", true);
			$handler->fullPath = $this->getConfig()->get("announce-full-path", false);
			$handler->dateFormat = $this->getConfig()->get("date-format", "d.m.Y (l): H:i:s [e]");

			$handler->submit();
			$this->getLogger()->debug("Crash dump sent");
		}
	}

	public function getCrashdumpFiles(): array{
		return glob($this->getServer()->getDataPath()."crashdumps/*.log");
	}
}
