<?php
declare(strict_types = 1);

namespace salmonde\crashlogger\utils;

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

	private string $webhookUrl;
	private CrashDumpReader $crashDumpReader;

	public bool $announceCrash = true;
	public bool $fullPath = false;
	public string $dateFormat = "d.m.Y (l): H:i:s [e]";

	public function __construct(string $webhookUrl, CrashDumpReader $crashDumpReader){
		$this->webhookUrl = $webhookUrl;
		$this->crashDumpReader = $crashDumpReader;
	}

	public function submit(): void{
		if(!$this->crashDumpReader->hasRead()){
			return;
		}

		$serverFolder = $this->fullPath ? Server::getInstance()->getDataPath() : basename(Server::getInstance()->getDataPath());
		if($this->announceCrash){
			$this->announceCrash($serverFolder);
		}

		$payload_json = [
			"content" => "Server \"".$serverFolder."\" crashed 👺",
			"embeds" => [$this->getEmbedData()]
		];

		$webhookData = [
			"payload_json" => json_encode($payload_json),
			"file" => trim(file_get_contents($this->crashDumpReader->getFilePath()))
		];

		$result = Internet::postURL($this->webhookUrl, $webhookData, 10, [
			"Content-Type" => "multipart/form-data",
			"Content-Disposition" => "form-data; name:\"file\"; filename=\"".$this->crashDumpReader->getFileName()."\""
		]);

		if($result->getCode() !== 204){
			Server::getInstance()->getPluginManager()->getPlugin("CrashLogger")->getLogger()->warning("Crash dump possibly not sent; Discord webhook api returned an unexpected http status code: ".$result->getCode());
		}
	}

	private function announceCrash(string $serverFolder): void{
		try{
			$webhookData = [
				"content" => "Crash detected in \"".$serverFolder."\""
			];

			Internet::postURL($this->webhookUrl, $webhookData, 10, ["Content-Type" => "application/json"]);
		}catch(\Throwable $e){
			Server::getInstance()->getPluginManager()->getPlugin("CrashLogger")->getLogger()->error("Error during crash announcement in file ".$e->getFile()." on line ".$e->getLine().": ".$e->getMessage());
		}
	}

	private function getEmbedData(): array{
		$crashData = $this->crashDumpReader->getData();

		if($crashData["uptime"] < 60){
			$uptime = round($crashData["uptime"], 2)." seconds";
		}elseif($crashData["uptime"] < 60 ** 2){
			$uptime = round($crashData["uptime"] / 60)." minutes";
		}elseif($crashData["uptime"] < 24 * 60 ** 2){
			$uptime = round($crashData["uptime"] / 3600)." hours";
		}else{
			$uptime = round($crashData["uptime"] / (24 * 60 ** 2))." days";
		}

		$faultyLine = $crashData["error"]["line"];
		$codeData = $crashData["code"];
		foreach($codeData as $line => $code){
			$codeData[$line] = ($line === $faultyLine ? ">" : " ")."[".$line."] ".$code;
		}
		$codeString = "```php\n";
		$stringEnding = "\n```";
		$codeString .= substr(implode("\n", $codeData), 0, 1024 - strlen($codeString.$stringEnding)).$stringEnding;

		$data = [
			"Exception Class" => $crashData["error"]["type"],
			"File" => "**".$crashData["error"]["file"]."**",
			"Line" => "**".$faultyLine."**",
			"Plugin involved" => $crashData["plugin_involvement"],
			"Plugin" => "**".($crashData["plugin"] ?? "?")."**",
			"Code" => $codeString,
			"Trace" => "```\n".substr(implode("\n", $crashData["trace"]), 0, 1024 - strlen("```\n".$stringEnding))."\n```",
			"Server Time" => date($this->dateFormat, (int) $crashData["time"]),
			"Server Uptime" => $uptime,
			"Server Git Commit" => "__".$crashData["general"]["git"]."__"
		];

		$fields = [];
		foreach($data as $fieldName => $fieldValue){
			$fields[] = [
				"name" => $fieldName,
				"value" => $fieldValue
			];
		}

		return [
			"color" => self::COLOURS[array_rand(self::COLOURS)],
			"title" => "Error: ".(substr($this->crashDumpReader->getData()["error"]["message"] ?? "Unknown error", 0, 256)),
			"fields" => $fields,
			"footer" => [
				"text" => "Sent by CrashLogger v".Server::getInstance()->getPluginManager()->getPlugin("CrashLogger")->getDescription()->getVersion()
			]
		];
	}
}
