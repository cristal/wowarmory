<?php

/**
 * @package World of Warcraft Armory
 * @version Release Candidate 1
 * @revision 440
 * @copyright (c) 2009-2011 Shadez
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 **/

define('__ARMORY__', true);
define('load_items_class', true);
define('load_mangos_class', true);
if(!@include('includes/armory_loader.php')) {
    die('<b>Fatal error:</b> unable to load system files.');
}
header('Content-type: text/xml');
$itemID = (isset($_GET['i'])) ? (int) $_GET['i'] : 0;
if(Armory::$armoryconfig['useCache'] == true && !isset($_GET['skipCache'])) {
    $cache_id = $utils->GenerateCacheId('item-info', $itemID, 0, Armory::$currentRealmInfo['name']);
    if($cache_data = $utils->GetCache($cache_id, 'items')) {
        echo $cache_data;
        echo sprintf('<!-- Restored from cache; id: %s -->', $cache_id);
        exit;
    }
}
// Load XSLT template
$xml->LoadXSLT('items/info.xsl');
$xml->XMLWriter()->startElement('page');
$xml->XMLWriter()->writeAttribute('globalSearch', 1);
$xml->XMLWriter()->writeAttribute('lang', Armory::GetLocale());
$xml->XMLWriter()->writeAttribute('requestUrl', 'item-info.xml');
$xml->XMLWriter()->writeAttribute('requestQuery', 'i='.$itemID);
if(!$items->IsItemExists($itemID) ) {
    $xml->XMLWriter()->startElement('itemInfo');
    $xml->XMLWriter()->endElement(); //itemInfo
    echo $xml->StopXML();
    exit;
}
// Do not query all rows - item data generated by item-tooltip.php
$data = $items->GetItemData($itemID);
$item_data = array(
    'icon'    => $items->GetItemIcon($itemID, $data['displayid']),
    'id'      => $itemID,
    'level'   => $data['ItemLevel'],
    'name'    => (Armory::GetLocale() == 'en_gb' || Armory::GetLocale() == 'en_us') ? $data['name'] : $items->GetItemName($itemID),
    'quality' => $data['Quality'],
    'type'    => null
);
$xml->XMLWriter()->startElement('itemInfo');
$xml->XMLWriter()->startElement('item');
foreach($item_data as $item_data_key => $item_data_value) {
    $xml->XMLWriter()->writeAttribute($item_data_key, $item_data_value);
}
$extended_cost = $mangos->GetVendorExtendedCost($itemID);
if($data['SellPrice'] > 0 || $data['BuyPrice'] || $extended_cost > 0) {
    $xml->XMLWriter()->startElement('cost');
    if($data['SellPrice'] > 0) {
        $xml->XMLWriter()->writeAttribute('sellPrice', $data['SellPrice']);
    }
    if($data['BuyPrice'] > 0 && $items->IsVendorItem($itemID)) {
        $xml->XMLWriter()->writeAttribute('buyPrice', $data['BuyPrice']);
    }
    $cost_info = $mangos->GetExtendedCost($extended_cost);
    $pvp_cost = $mangos->GetPvPExtendedCost($extended_cost);
    if(is_array($pvp_cost)) {
        foreach($pvp_cost as $pvp_cost_key => $pvp_cost_value) {
            if($pvp_cost_value > 0) {
                $xml->XMLWriter()->writeAttribute($pvp_cost_key, $pvp_cost_value);
            }
        }
        $xml->XMLWriter()->writeAttribute('factionId', ($data['Flags2']&ITEM_FLAGS2_HORDE_ONLY) ? FACTION_ALLIANCE : FACTION_HORDE);
    }
    if(is_array($cost_info)) {
        foreach($cost_info as $cost) {
            $xml->XMLWriter()->startElement('token');
            foreach($cost as $cost_key => $cost_value) {
                $xml->XMLWriter()->writeAttribute($cost_key, $cost_value);
            }
            $xml->XMLWriter()->endElement(); //token
        }
    }
    $xml->XMLWriter()->endElement(); //cost
}
if($disenchant_loot = $items->BuildLootTable($itemID, 'disenchant')) {
    $xml->XMLWriter()->startElement('disenchantLoot');
    if($data['RequiredDisenchantSkill'] > 0) {
        $xml->XMLWriter()->writeAttribute('requiredSkillRank', $data['RequiredDisenchantSkill']);
    }
    foreach($disenchant_loot as $disenchant_item) {
        $xml->XMLWriter()->startElement('item');
        foreach($disenchant_item as $d_item_key => $d_item_value) {
            $xml->XMLWriter()->writeAttribute($d_item_key, $d_item_value);
        }
        $xml->XMLWriter()->endElement(); //item
    }
    $xml->XMLWriter()->endElement(); //disenchantLoot
}
if($vendor_items = $items->BuildLootTable($itemID, 'vendor')) {
    $xml->XMLWriter()->startElement('vendors');
    foreach($vendor_items as $vendor) {
        $xml->XMLWriter()->startElement('creature');
        foreach($vendor as $v_item_key => $v_item_value) {
            $xml->XMLWriter()->writeAttribute($v_item_key, $v_item_value);
        }
        $xml->XMLWriter()->endElement(); //creature
    }
    $xml->XMLWriter()->endElement(); //vendors
}
if($currency_items = $items->BuildLootTable($itemID, 'currencyfor')) {
    $xml->XMLWriter()->startElement('currencyFor');
    foreach($currency_items as $item_currency) {
        $xml->XMLWriter()->startElement('item');
        foreach($item_currency['data'] as $cKey => $cValue) {
            $xml->XMLWriter()->writeAttribute($cKey, $cValue);
        }
        if(is_array($item_currency['tokens'])) {
            $xml->XMLWriter()->startElement('cost');
            foreach($item_currency['tokens'] as $token) {
                $xml->XMLWriter()->startElement('token');
                foreach($token as $tKey => $tValue) {
                    $xml->XMLWriter()->writeAttribute($tKey, $tValue);
                }
                $xml->XMLWriter()->endElement(); //$token
            }
            $xml->XMLWriter()->endElement(); //cost
        }
        $xml->XMLWriter()->endElement(); //item
    }
    $xml->XMLWriter()->endElement(); //currencyFor
}
if($creature_loot = $items->BuildLootTable($itemID, 'creature')) {
    $xml->XMLWriter()->startElement('dropCreatures');
    foreach($creature_loot as $creature_item) {
        $xml->XMLWriter()->startElement('creature');
        foreach($creature_item as $c_item_key => $c_item_value) {
            $xml->XMLWriter()->writeAttribute($c_item_key, $c_item_value);
        }
        $xml->XMLWriter()->endElement(); //creature
    }
    $xml->XMLWriter()->endElement(); //dropCreatures
}
if($gameobject_loot = $items->BuildLootTable($itemID, 'gameobject')) {
    $xml->XMLWriter()->startElement('containerObjects');
    foreach($gameobject_loot as $gameobject_item) {
        $xml->XMLWriter()->startElement('object');
        foreach($gameobject_item as $gobject_key => $gobject_value) {
            $xml->XMLWriter()->writeAttribute($gobject_key, $gobject_value);
        }
        $xml->XMLWriter()->endElement(); //object
    }
    $xml->XMLWriter()->endElement(); //containerObjects
}
//TODO: find way to optimize Items::BuildLootTable(id, `reagent` and `craft`) work
if($reagent_for = $items->BuildLootTable($itemID, 'reagent')) {
    $xml->XMLWriter()->startElement('reagentFor');
    foreach($reagent_for as $items_reagent) {
        $xml->XMLWriter()->startElement('spell');
        foreach($items_reagent['spell'] as $spell_key => $spell_value) {
            $xml->XMLWriter()->writeAttribute($spell_key, $spell_value);
        }
        unset($spell_key, $spell_value);
        foreach($items_reagent['item'] as $i_reagent) {
            $xml->XMLWriter()->startElement('item');
            foreach($i_reagent as $i_reagent_key => $i_reagent_value) {
                $xml->XMLWriter()->writeAttribute($i_reagent_key, $i_reagent_value);
            }
            $xml->XMLWriter()->endElement(); //item
        }
        unset($i_reagent, $i_reagent_key, $i_reagent_value);
        foreach($items_reagent['reagent'] as $reagent_item) {
            $xml->XMLWriter()->startElement('reagent');
            foreach($reagent_item as $r_item_key => $r_item_value) {
                $xml->XMLWriter()->writeAttribute($r_item_key, $r_item_value);
            }
            $xml->XMLWriter()->endElement(); //reagent
        }
        unset($reagent_item, $r_item_key, $r_item_value);
        $xml->XMLWriter()->endElement(); //spell
    }
    unset($items_reagent, $reagent_for);
    $xml->XMLWriter()->endElement(); //reagentFor
}
if($craft_item = $items->BuildLootTable($itemID, 'craft')) {
    $xml->XMLWriter()->startElement('createdBy');
    foreach($craft_item as $crafted_item) {
        $xml->XMLWriter()->startElement('spell');
        foreach($crafted_item['spell'] as $spell_craft_key => $spell_craft_value) {
            $xml->XMLWriter()->writeAttribute($spell_craft_key, $spell_craft_value);
        }
        unset($spell_craft_key, $spell_craft_value);
        foreach($crafted_item['item'] as $crafted_reagent) {
            $xml->XMLWriter()->startElement('item');
            foreach($crafted_reagent as $craft_reagent_key => $craft_reagent_value) {
                $xml->XMLWriter()->writeAttribute($craft_reagent_key, $craft_reagent_value);
            }
            $xml->XMLWriter()->endElement(); //item
        }
        unset($craft_reagent_key, $craft_reagent_value, $crafted_reagent);
        foreach($crafted_item['reagent'] as $reagent_craft_item) {
            $xml->XMLWriter()->startElement('reagent');
            foreach($reagent_craft_item as $cr_item_key => $cr_item_value) {
                $xml->XMLWriter()->writeAttribute($cr_item_key, $cr_item_value);
            }
            $xml->XMLWriter()->endElement(); //reagent
        }
        unset($reagent_craft_item, $cr_item_key, $cr_item_value);
        $xml->XMLWriter()->endElement(); //spell
    }
    unset($craft_item, $crafted_item);
    $xml->XMLWriter()->endElement(); //createdBy
}
$factionFlags = 0;
if($data['Flags2']&ITEM_FLAGS2_ALLIANCE_ONLY) {
    $factionFlags = 2;
}
elseif($data['Flags2']&ITEM_FLAGS2_HORDE_ONLY) {
    $factionFlags = 1;
}
if($factionFlags > 0) {
    $xml->XMLWriter()->startElement('translationFor');
    $xml->XMLWriter()->writeAttribute('factionEquiv', ($factionFlags == 1) ? 0 : 1);
    $equivalent_item = $items->GetFactionEquivalent($itemID, $factionFlags);
    if($equivalent_item) {
        $xml->XMLWriter()->startElement('item');
        foreach($equivalent_item as $eq_key => $eq_value) {
            $xml->XMLWriter()->writeAttribute($eq_key, $eq_value);
        }
        $xml->XMLWriter()->endElement();  //item
    }    
    $xml->XMLWriter()->endElement(); //translationFor
}
/* Random properties */
if($randProperties = $items->BuildLootTable($itemID, 'randomProperty')) {
    $xml->XMLWriter()->startElement('randomProperties');
    foreach($randProperties as $prop) {
        $xml->XMLWriter()->startElement('randomProperty');
        $xml->XMLWriter()->writeAttribute('suffix', $prop['name']);
        if(is_array($prop['data'])) {
            foreach($prop['data'] as $property) {
                $xml->XMLWriter()->startElement('randomPropertyEnchant');
                $xml->XMLWriter()->writeAttribute('name', $property);
                $xml->XMLWriter()->endElement();  //randomPropertyEnchant
            }
        }
        $xml->XMLWriter()->endElement(); //randomProperty
    }
    $xml->XMLWriter()->endElement(); //randomProperties
}
/* Quest info */
if($start_quest = $items->BuildLootTable($itemID, 'queststart')) {
    $xml->XMLWriter()->startElement('startsQuest');
    $xml->XMLWriter()->startElement('quest');
    foreach($start_quest as $start_q_key => $start_q_value) {
        $xml->XMLWriter()->writeAttribute($start_q_key, $start_q_value);
    }
    $xml->XMLWriter()->endElement();  //quest
    $xml->XMLWriter()->endElement(); //startsQuest
}
if($provided_for = $items->BuildLootTable($itemID, 'providedfor')) {
    $xml->XMLWriter()->startElement('providedForQuests');
    foreach($provided_for as $prov_quest) {
        $xml->XMLWriter()->startElement('quest');
        foreach($prov_quest as $prov_q_key => $prov_q_value) {
            $xml->XMLWriter()->writeAttribute($prov_q_key, $prov_q_value);
        }
        $xml->XMLWriter()->endElement(); //quest
    }
    $xml->XMLWriter()->endElement(); //providedForQuests
}
if($objective_of = $items->BuildLootTable($itemID, 'objectiveof')) {
    $xml->XMLWriter()->startElement('objectiveOfQuests');
    foreach($objective_of as $objective_quest) {
        $xml->XMLWriter()->startElement('quest');
        foreach($objective_quest as $objective_q_key => $objective_q_value) {
            $xml->XMLWriter()->writeAttribute($objective_q_key, $objective_q_value);
        }
        $xml->XMLWriter()->endElement(); //quest
    }
    $xml->XMLWriter()->endElement(); //objectiveOfQuests
}
if($quest_reward = $items->BuildLootTable($itemID, 'questreward')) {
    $xml->XMLWriter()->startElement('rewardFromQuests');
    foreach($quest_reward as $reward_quest) {
        $xml->XMLWriter()->startElement('quest');
        foreach($reward_quest as $reward_q_key => $reward_q_value) {
            $xml->XMLWriter()->writeAttribute($reward_q_key, $reward_q_value);
        }
        $xml->XMLWriter()->endElement(); //quest
    }
    $xml->XMLWriter()->endElement(); //rewardFromQuests
}
$xml->XMLWriter()->endElement();   //item
$xml->XMLWriter()->endElement();  //itemInfo
$xml->XMLWriter()->endElement(); //page
$xml_cache_data = $xml->StopXML();
echo $xml_cache_data;
if(Armory::$armoryconfig['useCache'] == true && !isset($_GET['skipCache'])) {
    // Write cache to file
    $cache_data = $utils->GenerateCacheData($itemID, 0, 'item-info');
    $cache_handler = $utils->WriteCache($cache_id, $cache_data, $xml_cache_data, 'items');
}
exit;
?>