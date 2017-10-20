<?php

/**
 * @author Frago9876543210
 * @link   https://github.com/Frago9876543210/PhoneInMcpe
 */

declare(strict_types=1);

namespace Frago9876543210\PhoneInMcpe;


use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\UUID;

class Main extends PluginBase implements Listener{

	/** @var string $model */
	public $model = '{"geometry.flat":{"bones":[{"name":"body","pivot":[0.0,0.0,0.0],"pos":[0.0,0.0,0.0],"rotation":[0.0,0.0,0.0],"cubes":[{"origin":[0.0,0.0,0.0],"size":[64.0,64.0,1.0],"uv":[0.0,0.0]}]}]}}';
	/** @var int $width */
	public $width = 7; //1920 / 64 = 30; 30 / 2 = 15; 15 / 2 = 7.5; 7
	/** @var int $height */
	public $height = 4; //1080 / 64 = 16.875; 16 / 2 = 8; 8 / 2 = 4
	/** @var  EntityInfo[] $entities */
	public $entities = [];
	/** @var EntityInfo[] $lastEntity */
	public $lastEntity = [];

	public function onEnable() : void{
		if(!extension_loaded("gd")){
			$this->getLogger()->error("[-] Turn on gd lib in php.ini or recompile php!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		if(!is_int($this->width) || !is_int($this->height)){
			$this->getLogger()->error("[-] You incorrectly calculated the size!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$path = $this->getDataFolder();
		if(file_exists($path . "tmp")){
			$this->removeDir($path . "tmp");
		}
		@mkdir($path . "tmp");

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		echo shell_exec('adb devices -l'); //for init phone & start adb server

		//If you have a productive PC, then you can use it:
		//$this->getServer()->getScheduler()->scheduleRepeatingTask(new UpdateImageTask($this), 1);
		//but this is better for small tests:
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new UpdateImageTask($this), 5);
	}

	public function chat(PlayerChatEvent $e) : void{
		$p = $e->getPlayer();
		$m = $e->getMessage();

		$args = explode(" ", $m);
		$word = array_shift($args);

		if($word === "start"){
			unset($this->entities);
			$e->setCancelled();
			$coordinates = $p->asVector3();
			$pitch = deg2rad($p->pitch);
			$yaw = deg2rad($p->yaw);
			$direction = new Vector3(-sin($yaw) * cos($pitch), -sin($pitch), cos($yaw) * cos($pitch));
			for($x = 1; $x < $this->width + 1; $x++){
				for($y = 1; $y < $this->height + 1; $y++){
					//NOTE: you can change size
					//0.125 * 4 = 0.5
					$pk = new AddPlayerPacket;
					$pk->uuid = $uuid = UUID::fromRandom();
					$pk->username = "";
					$pk->entityRuntimeId = $eid = Entity::$entityCount++;
					$pk->position = new Vector3($coordinates->x + $direction->x + ($x * 0.5), $coordinates->y + ($y * 0.5), $coordinates->z + $direction->z);
					$pk->motion = new Vector3;
					$pk->yaw = 0.0;
					$pk->pitch = 0.0;
					$pk->item = Item::get(Item::AIR);
					$pk->metadata = [
						Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.125],
						Entity::DATA_BOUNDING_BOX_WIDTH => [Entity::DATA_TYPE_FLOAT, 0],
						Entity::DATA_BOUNDING_BOX_HEIGHT => [Entity::DATA_TYPE_FLOAT, 0]
					];
					$p->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);

					$skinPk = new PlayerSkinPacket;
					$skinPk->uuid = $uuid;
					$skinPk->skin = new Skin("", str_repeat('Z', 16384), "", "geometry.flat", $this->model);
					$p->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $skinPk);
					//todo: check this
					$this->entities[] = new EntityInfo($eid, $uuid, $x * 4 * 64, $y * 4 * 64);
				}
			}
		}elseif($word === "touch"){
			$e->setCancelled();
			if(isset($args[0]) && isset($args[1])){
				$this->touch(intval($args[0]), intval($args[1]));
			}
		}elseif($word === "shell"){
			$e->setCancelled();
			shell_exec("adb shell " . implode(" ", $args));
		}elseif($word === "stop"){
			$e->setCancelled();
			foreach($this->entities as $entityInfo){
				$pk = new RemoveEntityPacket;
				$pk->entityUniqueId = $entityInfo->getEntityRuntimeId();
				$p->dataPacket($pk);
			}
			$this->entities = [];
		}
	}

	public function handleInteractPacket(DataPacketReceiveEvent $e) : void{
		//InventoryTransactionPacket works very rarely
		//InteractPacket better
		$packet = $e->getPacket();
		if($packet instanceof InteractPacket){
			$eid = $packet->target;
			foreach($this->entities as $entityInfo){
				if($entityInfo->getEntityRuntimeId() == $eid){
					$this->lastEntity[$e->getPlayer()->getName()] = $entityInfo;
				}
			}
		}
	}

	public function onPress(PlayerInteractEvent $e){
		if($e->getAction() == $e::RIGHT_CLICK_AIR && $e->getItem()->getId() == Item::STICK){
			$name = $e->getPlayer()->getName();
			if(isset($this->lastEntity[$name])){
				$this->touch($this->lastEntity[$name]->getHeight(), $this->lastEntity[$name]->getWidth());
			}
		}
	}

	/**
	 * Converts an image to bytes
	 * @param string $filename path to png
	 * @return string bytes for skin
	 */
	public function getTextureFromFile(string $filename) : string{
		$im = imagecreatefrompng($filename);
		list($width, $height) = getimagesize($filename);
		$bytes = "";
		for($y = 0; $y < $height; $y++){
			for($x = 0; $x < $width; $x++){
				$argb = imagecolorat($im, $x, $y);
				$a = ((~((int) ($argb >> 24))) << 1) & 0xff;
				$r = ($argb >> 16) & 0xff;
				$g = ($argb >> 8) & 0xff;
				$b = $argb & 0xff;
				$bytes .= chr($r) . chr($g) . chr($b) . chr($a);
			}
		}
		imagedestroy($im);
		return $bytes;
	}

	/**
	 * Recursive crop image
	 * @param string $image  path to png
	 * @param string $output path to output png
	 */
	public function cropRecursive(string $image, string $output) : void{
		//TODO: optimization
		$size = getimagesize($image);
		$im = imagecreatefrompng($image);
		$newIm = imagecreatetruecolor(64, 64);
		$i = 0;
		for($xc = 0; $xc <= $size[0]; $xc = $xc + 64){
			for($y = $size[1] - 64; $y >= 0; $y = $y - 64){
				imagecopy($newIm, $im, 0, 0, $xc, $y, 64, 64);
				imagepng($newIm, $output . $i++ . '.png');
			}
		}
		imagedestroy($newIm);
		imagedestroy($im);
	}

	/**
	 * Resizes the picture
	 * @param string $filename path to png
	 * @param string $output   path to output png
	 * @param int    $new_width
	 * @param int    $new_height
	 */
	public function resize(string $filename, string $output, int $new_width, int $new_height) : void{
		list($width, $height) = getimagesize($filename);
		$image_p = imagecreatetruecolor($new_width, $new_height);
		$image = imagecreatefrompng($filename);
		imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		imagepng($image_p, $output);
		imagedestroy($image);
		imagedestroy($image_p);
	}

	/**
	 * Remove directory
	 * @param string $dir
	 */
	private function removeDir(string $dir) : void{
		if($objects = glob($dir . "/*")){
			foreach($objects as $obj){
				is_dir($obj) ? $this->removeDir($obj) : unlink($obj);
			}
		}
		rmdir($dir);
	}

	/**
	 * @param int $x
	 * @param int $y
	 */
	public function touch(int $x, int $y) : void{
		shell_exec("adb shell input tap $x $y");
	}
}
