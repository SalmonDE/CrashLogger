<?php
declare(strict_types = 1);

namespace SalmonDE\CrashLogger;

use pocketmine\plugin\PluginBase;
use SalmonDE\CrashLogger\Utils\CrashDumpReader;
use SalmonDE\CrashLogger\Utils\DiscordHandler;

class Main extends PluginBase {

	public function onEnable(): void{
		$this->saveResource('config.yml');
		$this->checkOldCrashDumps();
	}

	public function onDisable(): void{
		$this->checkNewCrashDump();
	}

	private function checkOldCrashDumps(): void{
		$validityDuration = $this->getConfig()->get('validity-duration', 24) * 60 * 60;
		$delete = $this->getConfig()->get('delete-files', true);

		$files = $this->getCrashdumpFiles();
		$this->getLogger()->info('Checking old crash dumps (files: '.count($files).') ...');

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
				$this->getLogger()->warning('Error during file check of "'.basename($filePath).'": '.$e->getMessage().' in file '.$e->getFile().' on line '.$e->getLine());
				foreach(explode("\n", $e->getTraceAsString()) as $traceString){
					$this->getLogger()->debug('[ERROR] '.$traceString);
				}
			}
		}

		$fileAmount = count($files);
		$percentage = $fileAmount > 0 ? round($removed * 100 / $fileAmount, 2) : 'NAN';

		$message = 'Checks finished, Deleted files: '.$removed.' ('.$percentage.'%)';
		if($removed > 0){
			$this->getLogger()->notice($message);
		}else{
			$this->getLogger()->info($message);
		}
	}

	private function checkNewCrashDump(): void{
		$files = $this->getCrashdumpFiles();

		$startTime = (int) \pocketmine\START_TIME;
		foreach($files as $filePath){
			try{
				$crashDumpReader = new CrashDumpReader($filePath);

				if(!$crashDumpReader->hasRead() or $crashDumpReader->getCreationTime() < $startTime){
					continue;
				}

				(new DiscordHandler($crashDumpReader))->submit();
			}catch(\Throwable $e){
				$this->getLogger()->warning('Error while checking potentially new crash dump "'.basename($filePath).'": '.$e->getMessage().' in file '.$e->getFile().' on line '.$e->getLine());
				foreach(explode("\n", $e->getTraceAsString()) as $traceString){
					$this->getLogger()->debug('[ERROR] '.$traceString);
				}
			}
		}
	}

	public function getCrashdumpFiles(): array{
		return glob($this->getServer()->getDataPath().'crashdumps/*.log');
	}
}
