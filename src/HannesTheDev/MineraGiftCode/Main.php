<?php

namespace HannesTheDev\MineraGiftCode;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerQuitEvent;

class Main extends PluginBase implements Listener
{

    public $used;
    public $eco;
    public $giftcode;
    public $instance;
    public $formCount = 0;
    public $forms = [];
    const PREFIX = "§8[§6§lMinera§r§8] §r";

    public function onEnable()
    {
        $this->getLogger()->info("Plugin activated!");
        $plugin = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        if (is_null($plugin)) {
            $this->getLogger()->info("You must installing EconomyAPI!");
            $this->getServer()->shutdown();
        } else {
            $this->eco = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
        }
        $this->formCount = rand(0, 0xFFFFFFFF);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }
        $this->used = new \SQLite3($this->getDataFolder() . "used-code.db");
        $this->used->exec("CREATE TABLE IF NOT EXISTS code (code);");
        $this->giftcode = new \SQLite3($this->getDataFolder() . "code.dn");
        $this->giftcode->exec("CREATE TABLE IF NOT EXISTS code (code);");
    }

    public function formCountBump(): void
    {
        ++$this->formCount;
        if ($this->formCount & (1 << 32)) {
            $this->formCount = rand(0, 0xFFFFFFFF);
        }
    }

    public function onPacketReceived(DataPacketReceiveEvent $ev): void
    {
        $pk = $ev->getPacket();
        if ($pk instanceof ModalFormResponsePacket) {
            $player = $ev->getPlayer();
            $formId = $pk->formId;
            $data = json_decode($pk->formData, true);
            if (isset($this->forms[$formId])) {
                $form = $this->forms[$formId];
                if (!$form->isRecipient($player)) {
                    return;
                }
                $callable = $form->getCallable();
                if (!is_array($data)) {
                    $data = [$data];
                }
                if ($callable !== null) {
                    $callable($ev->getPlayer(), $data);
                }
                unset($this->forms[$formId]);
                $ev->setCancelled();
            }
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $ev)
    {
        $player = $ev->getPlayer();
        foreach ($this->forms as $id => $form) {
            if ($form->isRecipient($player)) {
                unset($this->forms[$id]);
                break;
            }
        }
    }

    public function RedeemMenu($player)
    {
        if ($player instanceof Player) {
            $form = new CustomForm(function (Player $player, $data = null) {
                $result = $data[0];
                if ($result != null) {
                    if ($this->codeExists($this->giftcode, $result)) {
                        if (!($this->codeExists($this->used, $result))) {
                            $chance = mt_rand(1, 5);
                            $this->addCode($this->used, $result);
                            switch ($chance) {
                                default:
                                    $player->sendMessage(Main::PREFIX . "§7You've successfully §aredeem §7the code and get now §a20.000 Dollar§7!");
                                    $this->eco->addMoney($player->getName(), 20000);
                                    break;
                            }
                        } else {
                            $player->sendMessage(Main::PREFIX . "§cYou've already used this code!");
                        }
                    } else {
                        $player->sendMessage(Main::PREFIX . "§cThe gift code you used was not found!");
                    }
                } else {
                    $player->sendMessage(Main::PREFIX . "§8[§cGiftCode§8] §cYou've must write a code in the line to get a gift!");
                }
            });
            $form->setTitle("§8[§cRedeemUI§8]");
            $form->addInput("§7Wrote the code below the line:");
            $form->sendToPlayer($player);
            return $form;
        }
    }

    public static function getInstance()
    {
        return true;
    }

    public function generateCode()
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $length = 10;
        $randomString = '2021';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        $this->addCode($this->giftcode, $randomString);
        return $randomString;
    }

    public function codeExists($file, $code)
    {
        $query = $file->query("SELECT * FROM code WHERE code='$code';");
        $ar = $query->fetchArray(SQLITE3_ASSOC);
        if (!empty($ar)) {
            return true;
        } else {
            return false;
        }
    }

    public function addCode($file, $code)
    {
        $stmt = $file->prepare("INSERT OR REPLACE INTO code (code) VALUES (:code);");
        $stmt->bindValue(":code", $code);
        $stmt->execute();
    }

    public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "gencode":
                if ($player->hasPermission("mineragiftcode.gencode.cmd")) {
                    $code = $this->generateCode();
                    $player->sendMessage(Main::PREFIX . "§7You've successfully §agenerated §7a gift code! §aCode: §c" . $code);
                } else {
                    $player->sendMessage(Main::PREFIX . "§cYou haven't permission to use this command!");
                }
                break;
            case "redeem":
                if ($player instanceof Player) {
                    $this->RedeemMenu($player);
                } else {
                    $player->sendMessage(Main::PREFIX . "§cYou must be a player to use this command!");
                }
                break;
        }
        return true;
    }
}
