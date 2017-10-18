<?php

/**
 * @author Frago9876543210
 * @link   https://github.com/Frago9876543210/PhoneInMcpe
 */

declare(strict_types=1);

namespace Frago9876543210\PhoneInMcpe;


use pocketmine\entity\Skin;
use pocketmine\network\mcpe\protocol\PlayerSkinPacket;
use pocketmine\scheduler\PluginTask;
use pocketmine\Server;

class UpdateImageTask extends PluginTask{
	/** @var  Main $plugin */
	private $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}

	public function onRun(int $currentTick) : void{
		if(!empty($this->plugin->entities)){
			$path = $this->getServer()->getDataPath();
			shell_exec('adb shell screencap -p /sdcard/s.png && adb pull /sdcard/s.png ' . $path . 's.png');
			$this->plugin->resize($path . 's.png', $path . 's1.png', $this->plugin->width * 64, $this->plugin->height * 64);
			$this->plugin->cropRecursive($path . 's1.png', $this->plugin->getDataFolder() . DIRECTORY_SEPARATOR . "tmp/p");
			$index = 0;
			foreach($this->plugin->entities as $UUID){
				$pk = new PlayerSkinPacket;
				$pk->uuid = $UUID;
				$pk->skin = new Skin(rand() . "", $this->plugin->getTextureFromFile($this->plugin->getDataFolder() . DIRECTORY_SEPARATOR . 'tmp/p' . $index++ . '.png'), "", "geometry.flat", $this->plugin->model);
				$this->getServer()->broadcastPacket($this->getServer()->getOnlinePlayers(), $pk);
			}
		}
	}

	public function getServer() : Server{
		return $this->plugin->getServer();
	}
}