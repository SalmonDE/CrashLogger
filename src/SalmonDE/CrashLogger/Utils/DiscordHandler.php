<?php
declare(strict_types = 1);

namespace SalmonDE\CrashLogger\Utils;

use pocketmine\CrashDump;
use pocketmine\Server;
use pocketmine\utils\Internet;

class DiscordHandler {

	private const COLOURS = [
		16761035,
		346726,
		15680081,
		6277471,
		16439902
	];

	private $webhookUrl;
	private $crashDumpReader;

	private $announceCrash = true;
	private $fullPath = false;

	public function __construct(string $webhookUrl, CrashDumpReader $crashDumpReader, bool $announceCrash = true, bool $fullPath = false){
		$this->webhookUrl = $webhookUrl;
		$this->crashDumpReader = $crashDumpReader;

		$this->announceCrash = $announceCrash;
		$this->fullPath = $fullPath;
	}

	public function submit(): void{
		if(!$this->crashDumpReader->hasRead()){
			return;
		}

		$serverFolder = $this->fullPath ? Server::getInstance()->getDataPath() : basename(Server::getInstance()->getDataPath());
		if($this->announceCrash){
			$this->announceCrash($serverFolder);
		}

		$webhookData = [];
		$webhookData['content'] = 'Server "'.$serverFolder.'" crashed 👺';

		$crashData = $this->crashDumpReader->getData();

		$infoString = $this->getInfoString($crashData);
		$codeString = $this->getCodeString($crashData);
		$traceString = $this->getTraceString($crashData);

		$webhookData['embeds'][] = [
			'color' => self::COLOURS[array_rand(self::COLOURS)],
			'title' => substr($crashData['error']['message'] ?? 'Unknown error', 0, 256),
			'fields' => [
				[
					'name' => 'Info',
					'value' => substr($infoString, 0, 1024),
					'inline' => true
				],
				[
					'name' => 'Code',
					'value' => substr($codeString, 0, 1024),
					'inline' => true
				],
				[
					'name' => 'Trace',
					'value' => substr($traceString, 0, 1024),
					'inline' => true
				]
			]
		];

		Internet::postURL($this->webhookUrl, json_encode($webhookData), 10, ['Content-Type' => 'application/json']);
	}

	protected function getInfoString(array $crashData): string{
		$infoString  =      'File: **'.$crashData['error']['file'].'**';
		$infoString .= "\n".'Line: **'.$crashData['error']['line'].'**';
		$infoString .= "\n".'Type: '.$crashData['error']['type'];
		$infoString .= "\n".'Time: '.date('d.m.Y (l): H:i:s [e]');
		$infoString .= "\n".'Plugin involved: '.$crashData['plugin_involvement'];
		$infoString .= "\n".'Plugin: **'.($crashData['plugin'] ?? '?').'**';
		$infoString .= "\n".'Git commit: __'.$crashData['general']['git'].'__';

		return $infoString;
	}

	protected function getCodeString(array $crashData): string{
		$codeString = '```php';

		$faultyLine = $crashData['error']['line'];
		foreach($crashData['code'] as $line => $code){
			$codeLine = ($line === $faultyLine ? '>' : ' ').'['.$line.'] '.$code;
			$codeString .= "\n".$codeLine;
		}

		$stringEnding = "\n".'```';
		$codeString = substr($codeString, 0, 1024 - strlen($stringEnding));
		$codeString .= $stringEnding;

		return $codeString;
	}

	protected function getTraceString(array $crashData): string{
		foreach($crashData['trace'] as $trace){
			if(!isset($traceString)){
				$traceString = $trace;
				continue;
			}

			$traceString .= "\n".$trace;
		}

		return $traceString;
	}

	final private function announceCrash(string $serverFolder): void{
		try{
			$webhookData = [
				'content' => 'Crash detected in "'.$serverFolder.'"'
			];

			Internet::postURL($this->webhookUrl, json_encode($webhookData), 10, ['Content-Type' => 'application/json']);
		}catch(\Throwable $e){
			Server::getInstance()->getPluginManager()->getPlugin('CrashLogger')->getLogger()->error('Error during crash announcement in file '.$e->getFile().' on line '.$e->getLine().': '.$e->getMessage());
		}
	}
}
