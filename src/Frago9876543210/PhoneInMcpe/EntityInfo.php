<?php

/**
 * @author Frago9876543210
 * @link   https://github.com/Frago9876543210/PhoneInMcpe
 */

declare(strict_types=1);

namespace Frago9876543210\PhoneInMcpe;


use pocketmine\utils\UUID;

class EntityInfo{
	/** @var  int */
	private $entityRuntimeId;
	/** @var  UUID */
	private $UUID;
	/** @var  int */
	private $width;
	/** @var  int */
	private $height;

	public function __construct(int $entityRuntimeId, UUID $UUID, int $width, int $height){
		$this->entityRuntimeId = $entityRuntimeId;
		$this->UUID = $UUID;
		$this->width = $width;
		$this->height = $height;
	}

	public function getEntityRuntimeId() : int{
		return $this->entityRuntimeId;
	}

	public function getUUID() : UUID{
		return $this->UUID;
	}

	public function getWidth() : int{
		return $this->width;
	}

	public function getHeight() : int{
		return $this->height;
	}
}