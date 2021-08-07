<?php
declare(strict_types = 1);

namespace salmonde\crashlogger\utils;

use RuntimeException;
use function base64_decode;
use function fclose;
use function fgets;
use function fopen;
use function is_array;
use function json_decode;
use function trim;
use function zlib_decode;

class CrashDumpReader {

	private string $filePath;
	private ?array $data = null;

	public function __construct(string $filePath){
		$this->filePath = $filePath;
		$this->readData();
	}

	private function readData(): void{
		$fileHandle = fopen($this->filePath, "r");

		$start = false;
		$end = false;

		$data = "";
		while($line = fgets($fileHandle)){
			$line = trim($line);

			if($start === true){
				if($end === false){
					if($line === "===END CRASH DUMP==="){
						$end = true;
						break;
					}else{
						$data .= $line;
					}
				}
			}elseif($line === "===BEGIN CRASH DUMP==="){
				$start = true;
			}
		}
		fclose($fileHandle);

		if($start === true and $end === true and trim($data) !== ""){
			$data = base64_decode($data);
			$data = zlib_decode($data);
			$data = json_decode($data, true);
			$this->data = $data;
		}
	}

	public function hasRead(): bool{
		return is_array($this->data);
	}

	public function getFilePath(): string{
		return $this->filePath;
	}

	public function getFileName(): string{
		return basename($this->getFilePath());
	}

	public function getData(): ?array{
		return $this->data;
	}

	public function getCreationTime(): float{
		if(!$this->hasRead()){
			throw new RuntimeException("No data was read");
		}

		return (float) $this->data["time"];
	}
}
