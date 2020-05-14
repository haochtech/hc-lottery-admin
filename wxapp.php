<?php

defined("IN_IA") or exit("Access Denied");
class yzcj_sunModuleWxapp extends WeModuleWxapp
{
	public function doPageOpenid()
	{
		global $_W, $_GPC;
		$res = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$code = $_GPC["code"];
		$appid = $res["appid"];
		$secret = $res["appsecret"];
		$url = "https://api.weixin.qq.com/sns/jscode2session?appid=" . $appid . "&secret=" . $secret . "&js_code=" . $code . "&grant_type=authorization_code";
		function httpRequest($url, $data = null)
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
			if (!empty($data)) {
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			}
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($curl);
			curl_close($curl);
			return $output;
		}
		$re = httpRequest($url);
		print_r($re);
	}
	public function doPageLogin()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$res = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]));
		if ($openid and $openid != "undefined") {
			if ($res) {
				$user_id = $res["id"];
				$data["openid"] = $_GPC["openid"];
				$data["img"] = $_GPC["img"];
				$data["name"] = $this->emoji_encode($_GPC["name"]);
				$res = pdo_update("yzcj_sun_user", $data, array("id" => $user_id, "uniacid" => $_W["uniacid"]));
				$user = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]));
				echo json_encode($user);
			} else {
				$data["openid"] = $_GPC["openid"];
				$data["img"] = $_GPC["img"];
				$data["name"] = $this->emoji_encode($_GPC["name"]);
				$data["uniacid"] = $_W["uniacid"];
				$data["time"] = date("Y-m-d H:i:s", time());
				$res2 = pdo_insert("yzcj_sun_user", $data);
				$user = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]));
				echo json_encode($user);
			}
		}
	}
	function emoji_encode($nickname)
	{
		$strEncode = '';
		$length = mb_strlen($nickname, "utf-8");
		for ($i = 0; $i < $length; $i++) {
			$_tmpStr = mb_substr($nickname, $i, 1, "utf-8");
			if (strlen($_tmpStr) >= 4) {
				$strEncode .= "[[EMOJI:" . rawurlencode($_tmpStr) . "]]";
			} else {
				$strEncode .= $_tmpStr;
			}
		}
		return $strEncode;
	}
	function emoji_decode($str)
	{
		$strDecode = preg_replace_callback("|\\[\\[EMOJI:(.*?)\\]\\]|", function ($matches) {
			return rawurldecode($matches[1]);
		}, $str);
		return $strDecode;
	}
	public function doPageSetTimeout()
	{
		global $_GPC, $_W;
		$goods = pdo_getall("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "status" => "2"));
		$goods = $this->sliceArr($goods);
		$goodsPro = [];
		$orderAll = [];
		$orderProYes = [];
		$orderProNo = [];
		$orderFail = [];
		$day = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "is_open_pop")["is_open_pop"];
		if (!$day || $day == 0) {
			$day = 3;
		}
		$garr = [];
		foreach ($goods as $key => $value) {
			$gid = $value["gid"];
			if ($value["condition"] == 0) {
				$nowtime = time();
				$endtime = strtotime($value["accurate"]);
				if ($nowtime >= $endtime) {
					$data["status"] = 4;
					$data["endtime"] = date("Y-m-d", time());
					$res = pdo_update("yzcj_sun_goods", $data, array("gid" => $value["gid"], "uniacid" => $_W["uniacid"]));
					$sql = "SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . "where a.uniacid=" . $_W["uniacid"] . " and a.status=" . "'1' and a.gid= '{$gid}' and a.uniacid=" . $_W["uniacid"];
					$order = pdo_fetchall($sql);
					if (!empty($order)) {
						$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
						if ($value["cid"] == 2 && $total < $value["count"]) {
							$count = $value["count"] - $total;
							$money = $value["gname"] * $count;
							$sid = $value["sid"];
							if (!empty($sid)) {
								$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["uid"];
							} else {
								$uid = $value["uid"];
							}
							$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
							$nowmoney = $usermoney + $money;
							$data1["money"] = $nowmoney;
							$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
						} else {
							if ($value["cid"] == 3 && $total < $value["count"]) {
								$count = $value["count"] - $total;
								$price = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $value["giftId"]), "price")["price"];
								$money = $price * $count;
								$sid = $value["sid"];
								if (!empty($sid)) {
									$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["uid"];
								} else {
									$uid = $value["uid"];
								}
								$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
								$nowmoney = $usermoney + $money;
								$data1["money"] = $nowmoney;
								$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
							}
						}
						array_push($orderAll, $order);
						$garr[] = $value["gid"];
					} else {
						if ($value["cid"] == 2) {
							$money = $value["gname"] * $value["count"];
							$sid = $value["sid"];
							if (!empty($sid)) {
								$uid = pdo_getall("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["0"]["uid"];
							} else {
								$uid = $value["uid"];
							}
							$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
							$nowmoney = $usermoney + $money;
							$data1["money"] = $nowmoney;
							$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
						} elseif ($value["cid"] == 3) {
							$price = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $value["giftId"]), "price")["price"];
							$money = $price * $value["count"];
							$sid = $value["sid"];
							if (!empty($sid)) {
								$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["uid"];
							} else {
								$uid = $value["uid"];
							}
							$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
							$nowmoney = $usermoney + $money;
							$data1["money"] = $nowmoney;
							$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
						}
					}
				}
			} elseif ($value["condition"] == 1) {
				$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
				$selftime = strtotime($value["selftime"]);
				$endtime = $selftime + $day * 24 * 60 * 60;
				$nowtime = time();
				if ($total >= $value["accurate"]) {
					$data["status"] = 4;
					$data["endtime"] = date("Y-m-d", time());
					$res = pdo_update("yzcj_sun_goods", $data, array("gid" => $value["gid"], "uniacid" => $_W["uniacid"]));
					$sql = "SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . "where a.uniacid=" . $_W["uniacid"] . " and a.status=" . "'1' and a.gid= '{$gid}'";
					$order = pdo_fetchall($sql);
					if (!empty($order)) {
						array_push($orderAll, $order);
						$garr[] = $value["gid"];
					}
				} else {
					if ($nowtime >= $endtime) {
						$data["status"] = 4;
						$data["endtime"] = date("Y-m-d", time());
						$res = pdo_update("yzcj_sun_goods", $data, array("gid" => $value["gid"], "uniacid" => $_W["uniacid"]));
						$sql = "SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . "where a.uniacid=" . $_W["uniacid"] . " and a.status=" . "'1' and a.gid= '{$gid}'";
						$order = pdo_fetchall($sql);
						if (!empty($order)) {
							$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
							if ($value["cid"] == 2 && $total < $value["count"]) {
								$count = $value["count"] - $total;
								$money = $value["gname"] * $count;
								$sid = $value["sid"];
								if (!empty($sid)) {
									$uid = pdo_getall("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["0"]["uid"];
								} else {
									$uid = $value["uid"];
								}
								$usermoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["0"]["money"];
								$nowmoney = $usermoney + $money;
								$data1["money"] = $nowmoney;
								$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
							} else {
								if ($value["cid"] == 3 && $total < $value["count"]) {
									$count = $value["count"] - $total;
									$price = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $value["giftId"]), "price")["price"];
									$money = $price * $count;
									$sid = $value["sid"];
									if (!empty($sid)) {
										$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["uid"];
									} else {
										$uid = $value["uid"];
									}
									$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
									$nowmoney = $usermoney + $money;
									$data1["money"] = $nowmoney;
									$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
								}
							}
							array_push($orderAll, $order);
							$garr[] = $value["gid"];
						} else {
							if ($value["cid"] == 2) {
								$money = $value["gname"] * $value["count"];
								$sid = $value["sid"];
								if (!empty($sid)) {
									$uid = pdo_getall("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["0"]["uid"];
								} else {
									$uid = $value["uid"];
								}
								$usermoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["0"]["money"];
								$nowmoney = $usermoney + $money;
								$data1["money"] = $nowmoney;
								$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
							} elseif ($value["cid"] == 3) {
								$price = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $value["giftId"]), "price")["price"];
								$money = $price * $value["count"];
								$sid = $value["sid"];
								if (!empty($sid)) {
									$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["uid"];
								} else {
									$uid = $value["uid"];
								}
								$usermoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
								$nowmoney = $usermoney + $money;
								$data1["money"] = $nowmoney;
								$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
							}
						}
					}
				}
			} elseif ($value["condition"] == 2) {
				$selftime = strtotime($value["selftime"]);
				$endtime = $selftime + 3 * 24 * 60 * 60;
				$nowtime = time();
				if ($nowtime >= $endtime) {
					$data["status"] = 5;
					$data["endtime"] = date("Y-m-d", time());
					$res = pdo_update("yzcj_sun_goods", $data, array("uniacid" => $_W["uniacid"], "gid" => $value["gid"]));
					$sql = "SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . "where a.uniacid=" . $_W["uniacid"] . " and a.status=" . "'1' and a.gid= '{$gid}'";
					$order = pdo_fetchall($sql);
					if (!empty($order)) {
						array_push($orderFail, $order);
						$garr[] = $value["gid"];
					} else {
						if ($value["cid"] == 2) {
							$money = $value["gname"] * $value["count"];
							$sid = $value["sid"];
							if (!empty($sid)) {
								$uid = pdo_getall("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $value["sid"]), "uid")["0"]["uid"];
							} else {
								$uid = $value["uid"];
							}
							$usermoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["0"]["money"];
							$nowmoney = $usermoney + $money;
							$data1["money"] = $nowmoney;
							$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
						}
					}
				}
			}
		}
		foreach ($orderFail as $key => $value) {
			foreach ($value as $k => $v) {
				$data1["status"] = 3;
				$result5 = pdo_update("yzcj_sun_order", $data1, array("uniacid" => $_W["uniacid"], "oid" => $v["oid"]));
			}
		}
		$ZZorderPro = [];
		$ZorderPro = [];
		foreach ($orderAll as $k => $v) {
			shuffle($v);
			foreach ($v as $ke => $val) {
				if ($val["zuid"] != 0) {
					$zcount = $val["count"] - 1;
					if ($val["zuid"] == $val["uid"]) {
						array_push($ZZorderPro, $val);
					} else {
						array_push($ZorderPro, $val);
					}
				} else {
					if ($val["zuid"] == 0) {
						array_push($orderProYes, array_slice($v, 0, $val["count"]));
						array_push($orderProNo, array_slice($v, $val["count"]));
					}
				}
			}
		}
		$res = $this->array_unique_fb($orderProYes);
		$res1 = $this->array_unique_fb($orderProNo);
		foreach ($ZZorderPro as $key => $value) {
			if ($value["cid"] == "2") {
				$money = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $value["uid"]), "money")["0"]["money"];
				$data3["money"] = $money + $value["gname"];
				$result1 = pdo_update("yzcj_sun_user", $data3, array("id" => $value["uid"], "uniacid" => $_W["uniacid"]));
			}
			$data4["status"] = 2;
			$result = pdo_update("yzcj_sun_order", $data4, array("oid" => $value["oid"], "uniacid" => $_W["uniacid"]));
		}
		$ZorderProYes = [];
		$ZorderProNo = [];
		foreach ($ZorderPro as $key => $value) {
			$zcount = $value["count"] - 1;
			$ZorderProYes = array_slice($ZorderPro, 0, $zcount);
			$ZorderProNo = array_slice($ZorderPro, $zcount);
		}
		foreach ($ZorderProYes as $key => $value) {
			if ($value["cid"] == 2) {
				$userid = $value["uid"];
				$umoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["0"]["money"];
				$nmoney = $umoney + $value["gname"];
				$data2["money"] = $nmoney;
				$result1 = pdo_update("yzcj_sun_user", $data2, array("id" => $userid, "uniacid" => $_W["uniacid"]));
			}
			$oid = $value["oid"];
			$data5["status"] = 2;
			$result2 = pdo_update("yzcj_sun_order", $data5, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		}
		foreach ($ZorderProNo as $key => $value) {
			$oid = $value["oid"];
			$data5["status"] = 4;
			$result2 = pdo_update("yzcj_sun_order", $data5, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		}
		foreach ($res as $x => $y) {
			foreach ($y as $z => $c) {
				if ($c["cid"] == "2") {
					$money = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $c["uid"]), "money")["0"]["money"];
					$data3["money"] = $money + $c["gname"];
					$result1 = pdo_update("yzcj_sun_user", $data3, array("id" => $c["uid"], "uniacid" => $_W["uniacid"]));
				}
				$data4["status"] = 2;
				$result = pdo_update("yzcj_sun_order", $data4, array("oid" => $c["oid"], "uniacid" => $_W["uniacid"]));
			}
		}
		foreach ($res1 as $e => $f) {
			foreach ($f as $g => $h) {
				$data4["status"] = 4;
				$result = pdo_update("yzcj_sun_order", $data4, array("oid" => $h["oid"], "uniacid" => $_W["uniacid"]));
			}
		}
		if (!empty($garr)) {
			echo json_encode($garr);
		}
	}
	public function array_unique_fb($array3D)
	{
		$tmp_array = array();
		$new_array = array();
		foreach ($array3D as $k => $val) {
			$hash = md5(json_encode($val));
			if (!in_array($hash, $tmp_array)) {
				$tmp_array[] = $hash;
				$new_array[] = $val;
			}
		}
		return $new_array;
	}
	public function doPageDoLottery()
	{
		global $_GPC, $_W;
		$gid = $_GPC["gid"];
		$goods = pdo_get("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "gid" => $gid));
		$order = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "gid" => $gid));
		if (!empty($order)) {
			$data["status"] = 4;
			$data["endtime"] = date("Y-m-d", time());
			$res = pdo_update("yzcj_sun_goods", $data, array("gid" => $gid, "uniacid" => $_W["uniacid"]));
			if ($goods["cid"] == 2) {
				$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
				if ($goods["count"] >= $total) {
					$count = $goods["count"] - $total;
					$money = $goods["gname"] * $count;
					$sid = $goods["sid"];
					if (!empty($sid)) {
						$uid = pdo_getall("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $goods["sid"]), "uid")["0"]["uid"];
					} else {
						$uid = $goods["uid"];
					}
					$usermoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["0"]["money"];
					$nowmoney = $usermoney + $money;
					$data1["money"] = $nowmoney;
					$result = pdo_update("yzcj_sun_user", $data1, array("id" => $uid, "uniacid" => $_W["uniacid"]));
					foreach ($order as $key => $value) {
						$userid = $value["uid"];
						$umoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["0"]["money"];
						$nmoney = $umoney + $goods["gname"];
						$data2["money"] = $nmoney;
						$result1 = pdo_update("yzcj_sun_user", $data2, array("id" => $userid, "uniacid" => $_W["uniacid"]));
						$oid = $value["oid"];
						$data3["status"] = 2;
						$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
					}
				} else {
					shuffle($order);
					$ZorderPro = [];
					if ($goods["zuid"] != 0) {
						foreach ($order as $key => $value) {
							if ($value["uid"] == $goods["zuid"]) {
								$userid = $value["uid"];
								$umoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["0"]["money"];
								$nmoney = $umoney + $goods["gname"];
								$data2["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data2, array("id" => $userid, "uniacid" => $_W["uniacid"]));
								$oid = $value["oid"];
								$data3["status"] = 2;
								$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
							} else {
								array_push($ZorderPro, $value);
							}
						}
						$zcount = $goods["count"] - 1;
						$orderProYes = array_slice($ZorderPro, 0, $zcount);
						$orderProNo = array_slice($ZorderPro, $zcount);
						foreach ($orderProYes as $key => $value) {
							$userid = $value["uid"];
							$umoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["0"]["money"];
							$nmoney = $umoney + $goods["gname"];
							$data2["money"] = $nmoney;
							$result1 = pdo_update("yzcj_sun_user", $data2, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							$oid = $value["oid"];
							$data3["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					} else {
						$orderProYes = array_slice($order, 0, $goods["count"]);
						$orderProNo = array_slice($order, $goods["count"]);
						foreach ($orderProYes as $key => $value) {
							$userid = $value["uid"];
							$umoney = pdo_getall("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["0"]["money"];
							$nmoney = $umoney + $goods["gname"];
							$data2["money"] = $nmoney;
							$result1 = pdo_update("yzcj_sun_user", $data2, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							$oid = $value["oid"];
							$data3["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					}
				}
			} else {
				shuffle($order);
				$ZorderPro = [];
				if ($goods["zuid"] != 0) {
					foreach ($order as $key => $value) {
						if ($value["uid"] == $goods["zuid"]) {
							$oid = $value["oid"];
							$data3["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						} else {
							array_push($ZorderPro, $value);
						}
					}
					$zcount = $goods["count"] - 1;
					$orderProYes = array_slice($ZorderPro, 0, $zcount);
					$orderProNo = array_slice($ZorderPro, $zcount);
					foreach ($orderProYes as $key => $value) {
						$oid = $value["oid"];
						$data3["status"] = 2;
						$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
					}
					foreach ($orderProNo as $key => $value) {
						$oid = $value["oid"];
						$data3["status"] = 4;
						$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
					}
				} else {
					$orderProYes = array_slice($order, 0, $goods["count"]);
					$orderProNo = array_slice($order, $goods["count"]);
					foreach ($orderProYes as $key => $value) {
						$oid = $value["oid"];
						$data3["status"] = 2;
						$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
					}
					foreach ($orderProNo as $key => $value) {
						$oid = $value["oid"];
						$data3["status"] = 4;
						$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
					}
				}
			}
		} else {
			echo json_encode(1);
		}
	}
	public function doPageProject()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$sql1 = "SELECT oid,gid FROM " . tablename("yzcj_sun_user") . "a left join " . tablename("yzcj_sun_order") . "b on b.uid=a.id where openid=" . "'{$openid}' and a.uniacid=" . $_W["uniacid"];
		$state = pdo_fetchall($sql1);
		$where = "where b.status=2 and b.sid!='' and a.uniacid=" . $_W["uniacid"];
		$res = pdo_fetchall("SELECT a.*,b.*,c.`sname` FROM " . tablename("yzcj_sun_goodsdaily") . " a" . " left join " . tablename("yzcj_sun_goods") . " b on b.gid=a.gid left join " . tablename("yzcj_sun_sponsorship") . " c on c.sid=b.sid " . $where . " ORDER BY a.id asc");
		$res = $this->sliceArr($res);
		foreach ($res as $key => $value) {
			foreach ($state as $k => $v) {
				if ($value["gid"] == $v["gid"]) {
					$res[$key]["oid"] = $v["oid"];
				}
			}
		}
		$support = pdo_getall("yzcj_sun_support", array("uniacid" => $_W["uniacid"]));
		$addnews = pdo_get("yzcj_sun_addnews", array("uniacid" => $_W["uniacid"]));
		//$whereNews = "where a.status=2 and DATE_SUB(CURDATE(), INTERVAL 3 DAY) <= date(a.time) and a.uniacid=" . $_W["uniacid"];
        $whereNews = "where a.status=2 and a.uniacid=" . $_W["uniacid"];
		$sqlNews = "select a.*,b.name,c.gname,c.cid from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid left join" . tablename("yzcj_sun_goods") . "c on c.gid=a.gid " . $whereNews;
		$resNews = pdo_fetchall($sqlNews);
		foreach ($resNews as $key => $value) {
			$resNews[$key]["name"] = $this->emoji_decode($resNews[$key]["name"]);
		}
		$ad = pdo_getall("yzcj_sun_ad", array("uniacid" => $_W["uniacid"], "status" => 1, "type" => 1));
		$popup = pdo_get("yzcj_sun_ad", array("uniacid" => $_W["uniacid"], "status" => 1, "type" => 2));
		$imgUrls = pdo_getall("yzcj_sun_banner", array("uniacid" => $_W["uniacid"]));
		$title = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sponsor = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "uid" => $uid));
		$info = array("res" => $res, "support" => $support, "addnews" => $addnews, "resNews" => $resNews, "ad" => $ad, "imgUrls" => $imgUrls, "sponsor" => $sponsor, "popup" => $popup, "title" => $title);
		echo json_encode($info);
	}
	public function doPageLuckyProject()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$sql1 = "SELECT oid,gid FROM " . tablename("yzcj_sun_user") . "a left join " . tablename("yzcj_sun_order") . "b on b.uid=a.id where openid=" . "'{$openid}' and a.uniacid=" . $_W["uniacid"];
		$state = pdo_fetchall($sql1);
		$whereAutomatic = "where a.status=2 and a.sid!='' and a.condition=0 and a.uniacid=" . $_W["uniacid"] . "|| a.status=2 and a.sid!='' and a.condition=1 and a.uniacid=" . $_W["uniacid"];
		$sqlAutomatic = "select a.*,b.sname from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_sponsorship") . "b on b.sid=a.sid " . $whereAutomatic . " ORDER BY a.selftime desc";
		$resAutomatic = pdo_fetchall($sqlAutomatic);
		$resAutomatic = $this->sliceArr($resAutomatic);
		foreach ($resAutomatic as $key => $value) {
			foreach ($state as $k => $v) {
				if ($value["gid"] == $v["gid"]) {
					$resAutomatic[$key]["oid"] = $v["oid"];
				}
			}
		}
		$whereManual = "where a.status=2 and a.sid!='' and a.condition=2 and a.uniacid=" . $_W["uniacid"];
		$sqlManual = "select a.*,b.sname from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_sponsorship") . "b on b.sid=a.sid " . $whereManual . " ORDER BY a.selftime desc";
		$resManual = pdo_fetchall($sqlManual);
		$resManual = $this->sliceArr($resManual);
		foreach ($resManual as $key => $value) {
			foreach ($state as $k => $v) {
				if ($value["gid"] == $v["gid"]) {
					$resManual[$key]["oid"] = $v["oid"];
				}
			}
		}
		$whereScene = "where a.status=2 and a.sid!='' and a.condition=3 and a.uniacid=" . $_W["uniacid"];
		$sqlScene = "select a.*,b.sname from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_sponsorship") . "b on b.sid=a.sid " . $whereScene . " ORDER BY a.selftime desc";
		$resScene = pdo_fetchall($sqlScene);
		$resScene = $this->sliceArr($resScene);
		foreach ($resScene as $key => $value) {
			foreach ($state as $k => $v) {
				if ($value["gid"] == $v["gid"]) {
					$resScene[$key]["oid"] = $v["oid"];
				}
			}
		}
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sponsor = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "uid" => $uid));
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$info = array("resAutomatic" => $resAutomatic, "resManual" => $resManual, "resScene" => $resScene, "sponsor" => $sponsor, "cjzt" => $cjzt);
		echo json_encode($info);
	}
	public function doPageProDetail()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$gid = $_GPC["gid"];
		$sid = pdo_get("yzcj_sun_goods", array("gid" => $gid, "uniacid" => $_W["uniacid"]), "sid")["sid"];
		if (!empty($sid)) {
			$where = "where a.gid=" . "'{$gid}' and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,a.status as astatus,b.* from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_sponsorship") . "b on b.sid=a.sid " . $where;
			$res = pdo_fetch($sql);
			$res["code_img"] = '';
		} else {
			$where = "where a.gid=" . "'{$gid}' and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,a.status as astatus,b.name from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid " . $where;
			$res = pdo_fetch($sql);
			$res["code_img"] = '';
		}
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$oid = pdo_get("yzcj_sun_order", array("uid" => $uid, "gid" => $gid, "uniacid" => $_W["uniacid"]), "oid")["oid"];
		if (!empty($oid)) {
			$res["oid"] = $oid;
		} else {
			$res["oid"] = 0;
		}
		$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$res["total"] = $total;
		$uidarr = pdo_fetchall("select uid from " . tablename("yzcj_sun_order") . " where gid = " . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$img = [];
		$img1 = [];
		shuffle($uidarr);
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] == $uid) {
				$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id='{$uid}' and uniacid=" . $_W["uniacid"]);
				array_push($img1, $res1);
			}
		}
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] != $uid) {
				if (count($img) < 6) {
					$id = $value["uid"];
					$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
					array_push($img, $res1);
				}
			}
		}
		$res["img"] = $img;
		$res["img1"] = $img1;
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$res["cjzt"] = $cjzt;
		$ad = pdo_getall("yzcj_sun_ad", array("uniacid" => $_W["uniacid"], "status" => 1, "type" => 1));
		shuffle($ad);
		$ad1 = [];
		array_push($ad1, array_slice($ad, 0, 1));
		$ZorderPro = [];
		if ($res["astatus"] == 2) {
			if ($res["condition"] == 1) {
				if ($res["accurate"] <= $total) {
					$data["status"] = 4;
					$data["endtime"] = date("Y-m-d", time());
					$result = pdo_update("yzcj_sun_goods", $data, array("gid" => $gid, "uniacid" => $_W["uniacid"]));
					$order = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "gid" => $gid));
					if ($res["zuid"] != 0) {
						foreach ($order as $key => $value) {
							if ($value["uid"] == $res["zuid"]) {
								if ($res["cid"] == 2) {
									$userid = $value["uid"];
									$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
									$nmoney = $umoney + $res["gname"];
									$data4["money"] = $nmoney;
									$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
								}
								$oid = $value["oid"];
								$data2["status"] = 2;
								$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
							} else {
								array_push($ZorderPro, $value);
							}
						}
						$zcount = $res["count"] - 1;
						shuffle($ZorderPro);
						$orderProYes = array_slice($ZorderPro, 0, $zcount);
						$orderProNo = array_slice($ZorderPro, $zcount);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result1 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					} else {
						shuffle($order);
						$orderProYes = array_slice($order, 0, $res["count"]);
						$orderProNo = array_slice($order, $res["count"]);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					}
					$info = array("num" => 10002);
					echo json_encode($info);
				} else {
					$info = array("num" => 10001, "res" => $res, "ad" => $ad1);
					echo json_encode($info);
				}
			} elseif ($res["condition"] == 0) {
				$nowtime = time();
				$endtime = strtotime($res["accurate"]);
				if ($nowtime >= $endtime) {
					$data["status"] = 4;
					$data["endtime"] = date("Y-m-d", time());
					$result = pdo_update("yzcj_sun_goods", $data, array("gid" => $gid, "uniacid" => $_W["uniacid"]));
					$order = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "gid" => $gid));
					if ($res["zuid"] != 0) {
						foreach ($order as $key => $value) {
							if ($value["uid"] == $res["zuid"]) {
								if ($res["cid"] == 2) {
									$userid = $value["uid"];
									$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
									$nmoney = $umoney + $res["gname"];
									$data4["money"] = $nmoney;
									$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
								}
								$oid = $value["oid"];
								$data2["status"] = 2;
								$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
							} else {
								array_push($ZorderPro, $value);
							}
						}
						$zcount = $res["count"] - 1;
						shuffle($ZorderPro);
						$orderProYes = array_slice($ZorderPro, 0, $zcount);
						$orderProNo = array_slice($ZorderPro, $zcount);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result1 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					} else {
						shuffle($order);
						$orderProYes = array_slice($order, 0, $res["count"]);
						$orderProNo = array_slice($order, $res["count"]);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					}
					$info = array("num" => 10002);
					echo json_encode($info);
				} else {
					$info = array("num" => 10001, "res" => $res, "ad" => $ad1);
					echo json_encode($info);
				}
			} else {
				$info = array("num" => 10001, "res" => $res, "ad" => $ad1);
				echo json_encode($info);
			}
		} else {
			$info = array("num" => 10002);
			echo json_encode($info);
		}
	}
	public function doPageProNum()
	{
		global $_GPC, $_W;
		$gid = $_GPC["gid"];
		$uid = $_GPC["uid"];
		$res = [];
		$img = [];
		$img1 = [];
		$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$res["total"] = $total;
		$uidarr = pdo_fetchall("select uid from " . tablename("yzcj_sun_order") . " where gid = " . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		shuffle($uidarr);
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] == $uid) {
				$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id='{$uid}' and uniacid=" . $_W["uniacid"]);
				array_push($img1, $res1);
			}
		}
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] != $uid) {
				$id = $value["uid"];
				$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
				array_push($img, $res1);
			}
		}
		$res["img"] = $img;
		$res["img1"] = $img1;
		echo json_encode($res);
	}
	public function doPageToupload()
	{
		global $_GPC, $_W;
		$uptypes = array("image/jpg", "image/jpeg", "image/png", "image/pjpeg", "image/gif", "image/bmp", "image/x-png");
		$max_file_size = 2000000;
		$year = date("Y/m", time());
		$destination_folder = "../attachment/";
		$watermark = 2;
		$watertype = 1;
		$waterposition = 1;
		$waterstring = "666666";
		$imgpreview = 1;
		$imgpreviewsize = 1 / 2;
		if (!is_uploaded_file($_FILES["file"]["tmp_name"])) {
			echo "图片不存在!";
			exit;
		}
		$file = $_FILES["file"];
		if ($max_file_size < $file["size"]) {
			echo "文件太大!";
			exit;
		}
		if (!in_array($file["type"], $uptypes)) {
			echo "文件类型不符!" . $file["type"];
			exit;
		}
		if (!file_exists($destination_folder)) {
			mkdir($destination_folder);
		}
		$filename = $file["tmp_name"];
		$image_size = getimagesize($filename);
		$pinfo = pathinfo($file["name"]);
		$ftype = $pinfo["extension"];
		$destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . "." . $ftype;
		if (file_exists($destination) && $overwrite != true) {
			echo "同名文件已经存在了";
			exit;
		}
		if (!move_uploaded_file($filename, $destination)) {
			echo "移动文件出错";
			exit;
		}
		$pinfo = pathinfo($destination);
		$fname = $pinfo["basename"];
		if ($watermark == 1) {
			$iinfo = getimagesize($destination, $iinfo);
			$nimage = imagecreatetruecolor($image_size[0], $image_size[1]);
			$white = imagecolorallocate($nimage, 255, 255, 255);
			$black = imagecolorallocate($nimage, 0, 0, 0);
			$red = imagecolorallocate($nimage, 255, 0, 0);
			imagefill($nimage, 0, 0, $white);
			switch ($iinfo[2]) {
				case 1:
					$simage = imagecreatefromgif($destination);
					break;
				case 2:
					$simage = imagecreatefromjpeg($destination);
					break;
				case 3:
					$simage = imagecreatefrompng($destination);
					break;
				case 6:
					$simage = imagecreatefromwbmp($destination);
					break;
				default:
					die("不支持的文件类型");
					exit;
			}
			imagecopy($nimage, $simage, 0, 0, 0, 0, $image_size[0], $image_size[1]);
			imagefilledrectangle($nimage, 1, $image_size[1] - 15, 80, $image_size[1], $white);
			switch ($watertype) {
				case 1:
					imagestring($nimage, 2, 3, $image_size[1] - 15, $waterstring, $black);
					break;
				case 2:
					$simage1 = imagecreatefromgif("xplore.gif");
					imagecopy($nimage, $simage1, 0, 0, 0, 0, 85, 15);
					imagedestroy($simage1);
					break;
			}
			switch ($iinfo[2]) {
				case 1:
					imagejpeg($nimage, $destination);
					break;
				case 2:
					imagejpeg($nimage, $destination);
					break;
				case 3:
					imagepng($nimage, $destination);
					break;
				case 6:
					imagewbmp($nimage, $destination);
					break;
			}
			imagedestroy($nimage);
			imagedestroy($simage);
		}
		echo $fname;
		@(require_once IA_ROOT . "/framework/function/file.func.php");
		@($filename = $fname);
		@file_remote_upload($filename);
	}
	public function doPageGetRed()
	{
		global $_GPC, $_W;
		$red = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		echo json_encode($red);
	}
	public function doPageAddPro()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"], "status" => 2), "sid")["sid"];
		$data["cid"] = $_GPC["awardtype"];
		$data["gName"] = $_GPC["gName"];
		$data["count"] = $_GPC["count"];
		$data["condition"] = $_GPC["index"];
		$data["accurate"] = $_GPC["accurate"];
		$data["selftime"] = date("Y-m-d H:i:s", time());
		$data["uniacid"] = $_W["uniacid"];
		$data["status"] = $_GPC["status"];
		$data["zuid"] = 0;
		if ($_GPC["imgSrc"] == '') {
			$data["pic"] = '';
		} else {
			$data["pic"] = $_GPC["imgSrc"];
		}
		if (!empty($sid)) {
			$data["sid"] = $sid;
			$res = pdo_insert("yzcj_sun_goods", $data);
		} else {
			$data["uid"] = $uid;
			$res = pdo_insert("yzcj_sun_goods", $data);
		}
		$gid = pdo_insertid();
		echo json_encode($gid);
	}
	public function doPageGetPi()
	{
		global $_GPC, $_W;
		$res = pdo_getall("yzcj_sun_goodspi", array("uniacid" => $_W["uniacid"]));
		echo json_encode($res);
	}
	public function doPageAddPI()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$data["gname"] = $_GPC["gname"];
		$data["count"] = $_GPC["count"];
		$data["cid"] = 4;
		$data["condition"] = $_GPC["index"];
		$data["accurate"] = $_GPC["accurate"];
		$data["selftime"] = date("Y-m-d H:i:s", time());
		$data["uniacid"] = $_W["uniacid"];
		$data["status"] = $_GPC["status"];
		$data["zuid"] = 0;
		if ($_GPC["imgSrc"] == '') {
			$data["pic"] = '';
		} else {
			$data["pic"] = $_GPC["imgSrc"];
		}
		$data["uid"] = $uid;
		$res = pdo_insert("yzcj_sun_goods", $data);
		$gid = pdo_insertid();
		echo json_encode($gid);
	}
	public function doPagegetGift()
	{
		global $_GPC, $_W;
		$giftId = $_GPC["giftId"];
		$count = $_GPC["count"];
		$gift = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $giftId));
		$gift["imgSrc"] = explode(",", $gift["pic"]);
		$gift["imgSrc"] = $gift["imgSrc"]["0"];
		if ($gift["count"] < $count) {
			$info = array("num" => "1", "gift" => $gift);
			echo json_encode($info);
		} else {
			echo 2;
		}
	}
	public function doPageAddGift()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"], "status" => 2), "sid")["sid"];
		$data["cid"] = $_GPC["awardtype"];
		$data["gName"] = $_GPC["gName"];
		$data["count"] = $_GPC["count"];
		$data["condition"] = $_GPC["index"];
		$data["accurate"] = $_GPC["accurate"];
		if ($_GPC["lottery"] == undefined) {
			$data["lottery"] = "大吉大利，送你好礼！";
		} else {
			$data["lottery"] = $_GPC["lottery"];
		}
		$data["selftime"] = date("Y-m-d H:i:s", time());
		$data["uniacid"] = $_W["uniacid"];
		$data["status"] = $_GPC["status"];
		$data["zuid"] = 0;
		if ($_GPC["imgSrc"] == '') {
			$data["pic"] = '';
		} else {
			$data["pic"] = $_GPC["imgSrc"];
		}
		$giftid = $_GPC["giftId"];
		$data["giftId"] = $giftid;
		$gifts = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $giftid));
		$data1["count"] = $gifts["count"] - $_GPC["count"];
		$res = pdo_update("yzcj_sun_gifts", $data1, array("id" => $giftid, "uniacid" => $_W["uniacid"]));
		if (!empty($sid)) {
			$data["sid"] = $sid;
			$res = pdo_insert("yzcj_sun_goods", $data);
		} else {
			$data["uid"] = $uid;
			$res = pdo_insert("yzcj_sun_goods", $data);
		}
		$gid = pdo_insertid();
		echo json_encode($gid);
	}
	public function doPagedelGift()
	{
		global $_GPC, $_W;
		$gid = $_GPC["gid"];
		$id = $_GPC["giftId"];
		$res = pdo_delete("yzcj_sun_goods", array("gid" => $_GPC["gid"], "uniacid" => $_W["uniacid"]));
		$count = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $id), "count")["count"];
		$data["count"] = $count + 1;
		$res = pdo_update("yzcj_sun_gifts", $data, array("id" => $id, "uniacid" => $_W["uniacid"]));
	}
	public function doPageEditor()
	{
		global $_GPC, $_W;
		$gid = $_GPC["gid"];
		$goods = pdo_get("yzcj_sun_goods", array("gid" => $gid, "uniacid" => $_W["uniacid"]));
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$goods["code_img"] = '';
		$goods["time"] = strtotime($goods["accurate"]);
		$info = array("goods" => $goods, "cjzt" => $cjzt);
		echo json_encode($info);
	}
	public function doPageTakePro()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$gid = $_GPC["gid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]));
		$order = pdo_get("yzcj_sun_order", array("gid" => $gid, "uid" => $uid["id"], "uniacid" => $_W["uniacid"]));
		if (empty($order)) {
			$data["orderNum"] = date("Ymdhi", time()) . rand(10000, 99999);
			$allNum = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"]), "orderNum");
			foreach ($allNum as $key => $value) {
				if ($value["orderNum"] == $data["orderNum"]) {
					$data["orderNum"] = date("Ymdhi", time()) . rand(10000, 99999);
				}
			}
			$data["time"] = date("Y-m-d H:i:s", time());
			$data["uid"] = $uid["id"];
			$data["gid"] = $gid;
			$data["uniacid"] = $_W["uniacid"];
			$res = pdo_insert("yzcj_sun_order", $data);
			echo json_encode($res);
		}
	}
	public function doPageMy()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$money = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "money")["money"];
		$allnum = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join" . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where a.uid=" . "'{$uid}' and a.status!=2 and a.status!=5 and a.status!=6 and b.cid !=3 and a.uniacid=" . $_W["uniacid"]);
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"]), "sid")["sid"];
		$launchnum1 = pdo_fetchcolumn("SELECT count(gid) FROM " . tablename("yzcj_sun_goods") . " where sid=" . "'{$sid}' and status!=1 and status!=3 and cid!=3 and uniacid=" . $_W["uniacid"]);
		$launchnum2 = pdo_fetchcolumn("SELECT count(gid) FROM " . tablename("yzcj_sun_goods") . " where uid=" . "'{$uid}' and status!=1 and status!=3 and cid!=3 and uniacid=" . $_W["uniacid"]);
		$launchnum = $launchnum1 + $launchnum2;
		$luckynum = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join" . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where a.uid='{$uid}' and a.status=2 and b.cid !=3 and a.uniacid=" . $_W["uniacid"] . "||a.uid='{$uid}' and a.status=5 and b.cid !=3 and a.uniacid=" . $_W["uniacid"] . "|| a.uid='{$uid}' and a.status=6 and b.cid !=3 and a.uniacid=" . $_W["uniacid"]);
		$res = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$info = array("money" => $money, "allnum" => $allnum, "launchnum" => $launchnum, "luckynum" => $luckynum, "res" => $res);
		echo json_encode($info);
	}
	public function doPageAllPro()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$status = $_GPC["status"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		if ($status == "1") {
			$where = "where a.uid=" . "'{$uid}' and a.status='1' and b.cid!=3  and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,b.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . $where . " ORDER BY a.time desc";
			$WaitPro = pdo_fetchall($sql);
			$WaitPro = $this->sliceArr($WaitPro);
			$where1 = "where a.uid=" . "'{$uid}' and a.status='4' and b.cid!=3 and a.uniacid=" . $_W["uniacid"];
			$sql1 = "select a.*,b.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . $where1 . " ORDER BY a.time desc";
			$OverPro = pdo_fetchall($sql1);
			$OverPro = $this->sliceArr($OverPro);
			$where2 = "where a.uid=" . "'{$uid}' and a.status='3' and b.cid!=3 and a.uniacid=" . $_W["uniacid"];
			$sql2 = "select a.*,b.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . $where2 . " ORDER BY a.time desc";
			$FailPro = pdo_fetchall($sql2);
			$FailPro = $this->sliceArr($FailPro);
		} else {
			$where = "where a.uid='{$uid}' and a.status='2' and b.cid!=3 and a.uniacid=" . $_W["uniacid"] . "|| a.uid='{$uid}' and a.status='5' and b.cid!=3 and a.uniacid=" . $_W["uniacid"] . "|| a.uid='{$uid}' and a.status='6' and b.cid!=3 and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,b.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . $where . " ORDER BY a.time desc";
			$WaitPro = pdo_fetchall($sql);
			$WaitPro = $this->sliceArr($WaitPro);
		}
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$info = array("WaitPro" => $WaitPro, "OverPro" => $OverPro, "FailPro" => $FailPro, "cjzt" => $cjzt);
		echo json_encode($info);
	}
	public function sliceArr($array)
	{
		foreach ($array as $k => $v) {
			$array[$k]["code_img"] = '';
		}
		return $array;
	}
	public function doPageIniPro()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"]), "sid")["sid"];
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		if (!empty($sid)) {
			$WaitPro = pdo_getall("yzcj_sun_goods", array("sid" => $sid, "uniacid" => $_W["uniacid"], "status" => "2", "cid !=" => "3"), array(), '', "selftime DESC");
			$WaitPro = $this->sliceArr($WaitPro);
			$OverPro = pdo_getall("yzcj_sun_goods", array("sid" => $sid, "uniacid" => $_W["uniacid"], "status" => "4", "cid !=" => "3"), array(), '', "endtime DESC");
			$OverPro = $this->sliceArr($OverPro);
			$FailPro = pdo_getall("yzcj_sun_goods", array("sid" => $sid, "uniacid" => $_W["uniacid"], "status" => "5", "cid !=" => "3"), array(), '', "selftime DESC");
			$FailPro = $this->sliceArr($FailPro);
		}
		$WaitPro1 = pdo_getall("yzcj_sun_goods", array("uid" => $uid, "uniacid" => $_W["uniacid"], "status" => "2", "cid !=" => "3"), array(), '', "selftime DESC");
		$WaitPro1 = $this->sliceArr($WaitPro1);
		$OverPro1 = pdo_getall("yzcj_sun_goods", array("uid" => $uid, "uniacid" => $_W["uniacid"], "status" => "4", "cid !=" => "3"), array(), '', "endtime DESC");
		$OverPro1 = $this->sliceArr($OverPro1);
		$FailPro1 = pdo_getall("yzcj_sun_goods", array("uid" => $uid, "uniacid" => $_W["uniacid"], "status" => "5", "cid !=" => "3"), array(), '', "selftime DESC");
		$FailPro1 = $this->sliceArr($FailPro1);
		$info = array("WaitPro" => $WaitPro, "OverPro" => $OverPro, "FailPro" => $FailPro, "WaitPro1" => $WaitPro1, "OverPro1" => $OverPro1, "FailPro1" => $FailPro1, "cjzt" => $cjzt);
		echo json_encode($info);
	}
	public function doPageMyGift()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"]), "sid")["sid"];
		$goods = pdo_getall("yzcj_sun_goods", array("uniacid" => $_W["uniacid"]));
		foreach ($goods as $key => $value) {
			if ($value["cid"] == 3) {
				if (empty($sid)) {
					$where = "where a.uid=" . "'{$uid}' and a.cid =3 and a.uniacid=" . $_W["uniacid"];
					$sql = "select a.*,a.status as statuss,a.count as count1,a.pic as img,b.* from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_gifts") . "b on b.id=a.giftId " . $where . " ORDER BY a.status asc";
					$res = pdo_fetchall($sql);
				} else {
					$where = "where a.sid=" . "'{$sid}' and a.cid =3  and a.uniacid=" . $_W["uniacid"] . "|| a.uid='{$uid}'  and a.cid =3 and a.uniacid=" . $_W["uniacid"];
					$sql = "select a.*,a.status as statuss,a.count as count1,a.pic as img,b.* from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_gifts") . "b on b.id=a.giftId " . $where . " ORDER BY a.status asc";
					$res = pdo_fetchall($sql);
				}
				$where1 = "where a.uid=" . "'{$uid}' and a.status!=2 and a.status!=5 and b.cid =3 and a.status!=6 and a.uniacid=" . $_W["uniacid"];
				$sql1 = "select a.*,a.status as statuss,b.*,b.count as count1,b.pic as img,c.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join " . tablename("yzcj_sun_gifts") . "c on c.id = b.giftId " . $where1 . " ORDER BY a.status asc";
				$WaitPro = pdo_fetchall($sql1);
				$where2 = "where a.uid=" . "'{$uid}' and a.status='2' and b.cid =3 and a.uniacid=" . $_W["uniacid"] . " || a.uid=" . "'{$uid}' and a.status='5' and b.cid =3 and a.uniacid=" . $_W["uniacid"] . " || a.uid=" . "'{$uid}' and a.status='6' and b.cid =3 and a.uniacid=" . $_W["uniacid"];
				$sql2 = "select a.*,a.status as statuss,b.*,b.count as count1,b.pic as img,c.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join " . tablename("yzcj_sun_gifts") . "c on c.id = b.giftId " . $where2 . " ORDER BY a.adid asc , a.status asc";
				$LuckyPro = pdo_fetchall($sql2);
			}
		}
		$info = array("res" => $res, "WaitPro" => $WaitPro, "LuckyPro" => $LuckyPro);
		echo json_encode($info);
	}
	public function doPageConfirm()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$where = "where a.oid='{$oid}' and a.uniacid=" . $_W["uniacid"];
		$sql = "select c.price,c.sid from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join " . tablename("yzcj_sun_gifts") . "c on c.id = b.giftId " . $where;
		$result = pdo_fetch($sql);
		$sid = $result["sid"];
		$uid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $sid), "uid")["uid"];
		$money = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $uid), "money")["money"];
		$data1["money"] = $money + $result["price"];
		$res1 = pdo_update("yzcj_sun_user", $data1, array("uniacid" => $_W["uniacid"], "id" => $uid));
		$data["status"] = 5;
		$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $oid));
	}
	public function doPagesure()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$data["status"] = 5;
		$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $oid));
	}
	public function doPageExamples()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$data["state"] = 2;
		$order = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $oid));
	}
	public function doPageAddOrder()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$gid = $_GPC["gid"];
		$oid = $_GPC["oid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		if ($oid == undefined || $oid == '') {
			$count = pdo_get("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "gid" => $gid), "count")["count"];
			$orderCount = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
			$order = pdo_get("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "gid" => $gid, "uid" => $uid));
			if (empty($order)) {
				if ($count > $orderCount) {
					$data["orderNum"] = date("Ymdhi", time()) . rand(10000, 99999);
					$allNum = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"]), "orderNum");
					foreach ($allNum as $key => $value) {
						if ($value["orderNum"] == $orderNum) {
							$data["orderNum"] = date("Ymdhi", time()) . rand(10000, 99999);
						}
					}
					$data["time"] = date("Y-m-d H:i:s", time());
					$data["uid"] = $uid;
					$data["gid"] = $gid;
					$data["status"] = 2;
					$data["uniacid"] = $_W["uniacid"];
					$res = pdo_insert("yzcj_sun_order", $data);
					echo 2;
				} else {
					echo 1;
				}
			} else {
				echo 1;
			}
		} else {
			$order = pdo_get("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "oid" => $oid));
			if ($order["state"] == 2) {
				echo 1;
			} else {
				$data["state"] = 2;
				$data["uid"] = $uid;
				$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $oid));
				echo 2;
			}
		}
	}
	public function doPageIniProDetail()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$gid = $_GPC["gid"];
		$userid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_goods", array("gid" => $gid, "uniacid" => $_W["uniacid"]), "sid")["sid"];
		if (empty($sid)) {
			$where = "where a.gid=" . "'{$gid}' and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,a.status as astatus,b.* from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid " . $where;
			$res = pdo_fetch($sql);
			$res["code_img"] = '';
			$oid = pdo_get("yzcj_sun_order", array("uid" => $userid, "gid" => $gid, "uniacid" => $_W["uniacid"]), "oid")["oid"];
		} else {
			$where = "where a.gid=" . "'{$gid}' and a.uniacid=" . $_W["uniacid"];
			$sql = "select a.*,a.status as astatus,b.* from " . tablename("yzcj_sun_goods") . "a left join " . tablename("yzcj_sun_sponsorship") . "b on b.sid=a.sid " . $where;
			$res = pdo_fetch($sql);
			$res["code_img"] = '';
			$usid = pdo_get("yzcj_sun_sponsorship", array("sid" => $sid, "uniacid" => $_W["uniacid"]), "uid")["uid"];
			$oid = pdo_get("yzcj_sun_order", array("uid" => $usid, "gid" => $gid, "uniacid" => $_W["uniacid"]), "oid")["oid"];
		}
		$total = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		if (!empty($oid)) {
			$res["oid"] = $oid;
		} else {
			$res["oid"] = 0;
		}
		$res["total"] = $total;
		$uidarr = pdo_fetchall("select uid from " . tablename("yzcj_sun_order") . " where gid = " . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$img = [];
		$img1 = [];
		shuffle($uidarr);
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] == $userid) {
				$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id='{$userid}' and uniacid=" . $_W["uniacid"]);
				array_push($img1, $res1);
			}
		}
		foreach ($uidarr as $key => $value) {
			if ($value["uid"] != $userid) {
				if (count($img) < 6) {
					$id = $value["uid"];
					$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
					array_push($img, $res1);
				}
			}
		}
		$res["img"] = $img;
		$res["img1"] = $img1;
		$total1 = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and adid!='' and uniacid=" . $_W["uniacid"]);
		$res["total1"] = $total1;
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$res["cjzt"] = $cjzt;
		$ad = pdo_getall("yzcj_sun_ad", array("uniacid" => $_W["uniacid"], "status" => 1, "type" => 1));
		shuffle($ad);
		$ad1 = [];
		array_push($ad1, array_slice($ad, 0, 1));
		$ZorderPro = [];
		if ($res["astatus"] == 2) {
			if ($res["condition"] == 1) {
				if ($res["accurate"] <= $total) {
					$data["status"] = 4;
					$data["endtime"] = date("Y-m-d", time());
					$result = pdo_update("yzcj_sun_goods", $data, array("gid" => $gid, "uniacid" => $_W["uniacid"]));
					$order = pdo_getall("yzcj_sun_order", array("uniacid" => $_W["uniacid"], "gid" => $gid));
					if ($res["zuid"] != 0) {
						foreach ($order as $key => $value) {
							if ($value["uid"] == $res["zuid"]) {
								if ($res["cid"] == 2) {
									$userid = $value["uid"];
									$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
									$nmoney = $umoney + $res["gname"];
									$data4["money"] = $nmoney;
									$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
								}
								$oid = $value["oid"];
								$data2["status"] = 2;
								$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
							} else {
								array_push($ZorderPro, $value);
							}
						}
						$zcount = $res["count"] - 1;
						shuffle($ZorderPro);
						$orderProYes = array_slice($ZorderPro, 0, $zcount);
						$orderProNo = array_slice($ZorderPro, $zcount);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result1 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result1 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					} else {
						shuffle($order);
						$orderProYes = array_slice($order, 0, $res["count"]);
						$orderProNo = array_slice($order, $res["count"]);
						foreach ($orderProYes as $key => $value) {
							if ($res["cid"] == 2) {
								$userid = $value["uid"];
								$umoney = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $userid), "money")["money"];
								$nmoney = $umoney + $res["gname"];
								$data4["money"] = $nmoney;
								$result1 = pdo_update("yzcj_sun_user", $data4, array("id" => $userid, "uniacid" => $_W["uniacid"]));
							}
							$oid = $value["oid"];
							$data2["status"] = 2;
							$result2 = pdo_update("yzcj_sun_order", $data2, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
						foreach ($orderProNo as $key => $value) {
							$oid = $value["oid"];
							$data3["status"] = 4;
							$result2 = pdo_update("yzcj_sun_order", $data3, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
						}
					}
					$info = array("num" => 10002);
					echo json_encode($info);
				}
			}
			$info = array("num" => 10001, "res" => $res, "ad" => $ad1);
			echo json_encode($info);
		} else {
			$info = array("num" => 10001, "res" => $res, "ad" => $ad1);
			echo json_encode($info);
		}
	}
	public function doPageLuckyTicket()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$gid = $_GPC["gid"];
		$oid = pdo_get("yzcj_sun_order", array("uid" => $uid, "uniacid" => $_W["uniacid"], "gid" => $gid), "oid")["oid"];
		$discount = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "discount");
		$where = "where a.oid=" . "'{$oid}' and a.uniacid=" . $_W["uniacid"];
		$sql = "select a.*,b.*,a.status as status2 from " . tablename("yzcj_sun_order") . "a join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid " . $where;
		$res = pdo_fetch($sql);
		$res["code_img"] = '';
		if ($res["sid"]) {
			$sid = $res["sid"];
			$sname = pdo_get("yzcj_sun_sponsorship", array("sid" => $sid, "uniacid" => $_W["uniacid"]), array("sname", "logo"));
			$res["name"] = $sname["sname"];
			$res["logo"] = $sname["logo"];
		} else {
			$uid1 = $res["uid"];
			$name = pdo_get("yzcj_sun_user", array("id" => $uid1, "uniacid" => $_W["uniacid"]), array("name", "img"));
			$res["name"] = $name["name"];
			$res["img"] = $name["img"];
		}
		$sql1 = pdo_fetchall("select b.name,b.img from " . tablename("yzcj_sun_order") . "a join " . tablename("yzcj_sun_user") . "b on b.id=a.uid where a.gid=" . "'{$gid}' and a.status=2  and a.uniacid=" . $_W["uniacid"] . "||a.gid=" . "'{$gid}' and a.status=5 and a.uniacid=" . $_W["uniacid"] . "||a.gid=" . "'{$gid}' and a.status=6 and a.uniacid=" . $_W["uniacid"]);
		foreach ($sql1 as $key => $value) {
			$sql1[$key]["name"] = $this->emoji_decode($sql1[$key]["name"]);
		}
		$res["nickName"] = $sql1;
		$allnum = pdo_fetchcolumn("SELECT count(gid) FROM " . tablename("yzcj_sun_order") . " where gid=" . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$res["allnum"] = $allnum;
		$uidArr = pdo_fetchall("select uid from " . tablename("yzcj_sun_order") . " where gid = " . "'{$gid}' and uniacid=" . $_W["uniacid"]);
		$img = [];
		foreach ($uidArr as $key => $value) {
			if (count($img) < 6) {
				$id = $value["uid"];
				$res1 = pdo_fetch("select img from " . tablename("yzcj_sun_user") . " where id=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
				array_push($img, $res1);
			}
		}
		$res["imgArr"] = $img;
		$res["discount"] = $discount["discount"];
		$cjzt = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "cjzt")["cjzt"];
		$res["cjzt"] = $cjzt;
		echo json_encode($res);
	}
	public function doPageDiscount()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$discount = $_GPC["discount"];
		$res = pdo_fetch("select a.uid,c.price from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join " . tablename("yzcj_sun_gifts") . "c on c.id = b.giftId where a.oid = '{$oid}' and a.uniacid= " . $_W["uniacid"]);
		$money = $res["price"] * $discount;
		$my = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "id" => $res["uid"]));
		$data["money"] = $money + $my["money"];
		$res1 = pdo_update("yzcj_sun_user", $data, array("uniacid" => $_W["uniacid"], "id" => $res["uid"]));
		$data1["status"] = 5;
		$result1 = pdo_update("yzcj_sun_order", $data1, array("uniacid" => $_W["uniacid"], "oid" => $oid));
	}
	public function doPageMyAddr()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$isAddr = pdo_get("yzcj_sun_address", array("uid" => $uid, "uniacid" => $_W["uniacid"]));
		$data["name"] = $_GPC["userName"];
		$data["uid"] = $uid;
		$data["telNumber"] = $_GPC["telNumber"];
		$data["postalCode"] = $_GPC["postalCode"];
		$data["provinceName"] = $_GPC["provinceName"];
		$data["cityName"] = $_GPC["cityName"];
		$data["countyName"] = $_GPC["countyName"];
		$data["detailAddr"] = $_GPC["detailInfo"];
		if (!$isAddr) {
			$data["uniacid"] = $_W["uniacid"];
			$res = pdo_insert("yzcj_sun_address", $data);
		} else {
			$res = pdo_update("yzcj_sun_address", $data, array("uid" => $uid, "uniacid" => $_W["uniacid"]));
		}
	}
	public function doPageShowAddr()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$gid = $_GPC["gid"];
		$oid = pdo_get("yzcj_sun_order", array("uid" => $uid, "gid" => $gid, "uniacid" => $_W["uniacid"]), "oid")["oid"];
		$address = pdo_getall("yzcj_sun_address", array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		echo json_encode($address);
	}
	public function doPageGetAddr()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$gid = $_GPC["gid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$oid = pdo_get("yzcj_sun_order", array("uid" => $uid, "gid" => $gid, "uniacid" => $_W["uniacid"]), "oid")["oid"];
		if ($_GPC["userName"] != undefined && $_GPC["detailAddr"] != undefined) {
			$data["uid"] = $uid;
			$data["oid"] = $oid;
			$data["name"] = $_GPC["userName"];
			$data["telNumber"] = $_GPC["telNumber"];
			$data["postalCode"] = $_GPC["postalCode"];
			$data["provinceName"] = $_GPC["provinceName"];
			$data["cityName"] = $_GPC["cityName"];
			$data["countyName"] = $_GPC["countyName"];
			$data["detailInfo"] = $_GPC["detailInfo"];
			$data["detailAddr"] = $_GPC["detailAddr"];
		} else {
			if ($_GPC["detailAddr"] != undefined && $_GPC["userName"] == undefined) {
				$data["detailAddr"] = $_GPC["detailAddr"];
			} else {
				if ($_GPC["detailAddr"] == undefined && $_GPC["userName"] != undefined) {
					$data["uid"] = $uid;
					$data["oid"] = $oid;
					$data["name"] = $_GPC["userName"];
					$data["telNumber"] = $_GPC["telNumber"];
					$data["postalCode"] = $_GPC["postalCode"];
					$data["provinceName"] = $_GPC["provinceName"];
					$data["cityName"] = $_GPC["cityName"];
					$data["countyName"] = $_GPC["countyName"];
					$data["detailInfo"] = $_GPC["detailInfo"];
				} else {
					$data["isDdfault"] = 0;
				}
			}
		}
		$isAddr = pdo_get("yzcj_sun_address", array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		if (!$isAddr) {
			$data["uniacid"] = $_W["uniacid"];
			$res = pdo_insert("yzcj_sun_address", $data);
			$adid = pdo_insertid();
			$data1["adid"] = $adid;
			$result = pdo_update("yzcj_sun_order", $data1, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		} else {
			$res = pdo_update("yzcj_sun_address", $data, array("oid" => $oid, "uniacid" => $_W["uniacid"]));
		}
		echo json_encode($oid);
	}
	public function doPageGetAddress()
	{
		global $_GPC, $_W;
		$gid = $_GPC["gid"];
		$adArr = pdo_fetchall("select * from " . tablename("yzcj_sun_order") . " where gid=" . $gid . " and  status = 2 and uniacid = " . $_W["uniacid"] . " || gid=" . $gid . " and  status =6 and uniacid = " . $_W["uniacid"]);
		$result = [];
		foreach ($adArr as $key => $value) {
			$adid = $value["adid"];
			$uid = $value["uid"];
			if (!empty($adid)) {
				$res = pdo_fetchall("select a.*,b.img from " . tablename("yzcj_sun_address") . "a join " . tablename("yzcj_sun_user") . "b on b.id=a.uid where a.adid=" . "'{$adid}' and a.uniacid=" . $_W["uniacid"]);
			} else {
				$res = pdo_getall("yzcj_sun_user", array("id" => $uid, "uniacid" => $_W["uniacid"]));
			}
			array_push($result, $res);
		}
		foreach ($result as $key => $value) {
			foreach ($value as $k => $v) {
				$v["name"] = $this->emoji_decode($v["name"]);
				if ($v["openid"]) {
					$resNo[] = $v;
				} else {
					if ($v["adid"]) {
						$resYes[] = $v;
					}
				}
			}
		}
		$Info = array("resNo" => $resNo, "resYes" => $resYes);
		echo json_encode($Info);
	}
	public function doPageNew()
	{
		global $_GPC, $_W;
		$res = pdo_get("yzcj_sun_addnews", array("uniacid" => $_W["uniacid"], "state" => 1));
		echo json_encode($res);
	}
	public function doPageHelp()
	{
		global $_GPC, $_W;
		$res = pdo_getall("yzcj_sun_help", array("uniacid" => $_W["uniacid"]));
		echo json_encode($res);
	}
	public function doPageGetSponsor()
	{
		global $_GPC, $_W;
		$res = pdo_get("yzcj_sun_sponsortext", array("uniacid" => $_W["uniacid"]));
		echo json_encode($res);
	}
	public function doPageAddSponsor()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$data["uid"] = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$data["sname"] = $_GPC["sname"];
		$data["phone"] = $_GPC["phone"];
		$data["wx"] = $_GPC["wx"];
		$data["status"] = 1;
		$data["uniacid"] = $_W["uniacid"];
		$res = pdo_insert("yzcj_sun_sponsorship", $data);
		echo json_encode($res);
	}
	public function doPageMySponsor()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uid" => $uid, "uniacid" => $_W["uniacid"]));
		if (!empty($sid)) {
			echo json_encode($sid);
		} else {
			echo json_encode($sid);
		}
	}
	public function doPagerenewal()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$data["status"] = 5;
		$res = pdo_update("yzcj_sun_sponsorship", $data, array("uniacid" => $_W["uniacid"], "sid" => $sid));
		if ($res) {
			echo 1;
		} else {
			echo 2;
		}
	}
	public function doPageUrl()
	{
		global $_GPC, $_W;
		echo $_W["attachurl"];
	}
	public function doPageUrl2()
	{
		global $_W, $_GPC;
		echo $_W["siteroot"];
	}
	public function doPageBalance()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$money = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]));
		$res = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$Info = array("money" => $money["money"], "res" => $res);
		echo json_encode($Info);
	}
	public function doPageGetBalance()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$user_id = $_GPC["uid"];
		$res = pdo_getall("yzcj_sun_withdrawal", array("user_id" => $user_id, "uniacid" => $_W["uniacid"]));
		foreach ($res as $key => $value) {
			$res[$key][] = date("Y-m-d H:i:s", $value["time"]);
		}
		echo json_encode($res);
	}
	public function doPageGoExtract()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$data["username"] = $_GPC["wx"];
		$data["name"] = $_GPC["name"];
		$data["user_id"] = $_GPC["uid"];
		$data["type"] = 2;
		$data["uniacid"] = $_W["uniacid"];
		$data["tx_cost"] = $_GPC["money"];
		if ($_GPC["money"] == 1) {
			$data["sj_cost"] = 1;
		} else {
			if ($_GPC["sj_cost"] < 1) {
				$data["sj_cost"] = 1;
			} else {
				$data["sj_cost"] = $_GPC["sj_cost"];
			}
		}
		$data["time"] = time();
		$data["state"] = 1;
		$res = pdo_insert("yzcj_sun_withdrawal", $data);
		$data1["money"] = 0;
		$result = pdo_update("yzcj_sun_user", $data1, array("openid" => $openid, "uniacid" => $_W["uniacid"]));
		echo json_encode($data1["money"]);
	}
	public function doPageOrderarr()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$appData = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$appid = $appData["appid"];
		$mch_id = $appData["mchid"];
		$keys = $appData["wxkey"];
		$price = $_GPC["price"];
		$order_url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$data = array("appid" => $appid, "mch_id" => $mch_id, "nonce_str" => "5K8264ILTKCH16CQ2502SI8ZNMTM67VS", "body" => time(), "out_trade_no" => date("Ymd") . substr('' . time(), -4, 4), "total_fee" => $price * 100, "spbill_create_ip" => "120.79.152.105", "notify_url" => "120.79.152.105", "trade_type" => "JSAPI", "openid" => $openid);
		ksort($data, SORT_ASC);
		$stringA = http_build_query($data);
		$signTempStr = $stringA . "&key=" . $keys;
		$signValue = strtoupper(md5($signTempStr));
		$data["sign"] = $signValue;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $order_url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->arrayToXml($data));
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$result = curl_exec($ch);
		curl_close($ch);
		$result = xml2array($result);
		echo json_encode($this->createPaySign($result));
	}
	function createPaySign($result)
	{
		global $_GPC, $_W;
		$appData = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$keys = $appData["wxkey"];
		$data = array("appId" => $result["appid"], "timeStamp" => (string) time(), "nonceStr" => $result["nonce_str"], "package" => "prepay_id=" . $result["prepay_id"], "signType" => "MD5");
		ksort($data, SORT_ASC);
		$stringA = '';
		foreach ($data as $key => $val) {
			$stringA .= "{$key}={$val}&";
		}
		$signTempStr = $stringA . "key=" . $keys;
		$signValue = strtoupper(md5($signTempStr));
		$data["paySign"] = $signValue;
		return $data;
	}
	function arrayToXml($arr)
	{
		$xml = "<xml>";
		foreach ($arr as $key => $val) {
			if (is_numeric($val)) {
				$xml .= "<" . $key . ">" . $val . "</" . $key . ">";
			} else {
				$xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
			}
		}
		$xml .= "</xml>";
		return $xml;
	}
	public function doPageSaveFormid()
	{
		global $_W, $_GPC;
		$Formid = pdo_get("yzcj_sun_userformid", array("gid" => $_GPC["gid"], "user_id" => $_GPC["user_id"], "uniacid" => $_W["uniacid"]));
		if (empty($Formid)) {
			$data["user_id"] = $_GPC["user_id"];
			$data["form_id"] = $_GPC["form_id"];
			$data["openid"] = $_GPC["openid"];
			$data["gid"] = $_GPC["gid"];
			$data["state"] = $_GPC["state"];
			$data["time"] = date("Y-m-d H:i:s");
			$data["uniacid"] = $_W["uniacid"];
			$res = pdo_insert("yzcj_sun_userformid", $data);
			if ($res) {
				echo "1";
			} else {
				echo "2";
			}
		}
	}
	public function doPageSaveFormid1()
	{
		global $_W, $_GPC;
		$data["user_id"] = $_GPC["user_id"];
		$data["form_id"] = $_GPC["form_id"];
		$data["openid"] = $_GPC["openid"];
		$data["gid"] = $_GPC["gid"];
		$data["state"] = $_GPC["state"];
		$data["time"] = date("Y-m-d H:i:s");
		$data["uniacid"] = $_W["uniacid"];
		$res = pdo_insert("yzcj_sun_userformid", $data);
		if ($res) {
			echo "1";
		} else {
			echo "2";
		}
	}
	public function doPageAccessToken()
	{
		global $_W, $_GPC;
		$res = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$code = $_GPC["code"];
		$appid = $res["appid"];
		$secret = $res["appsecret"];
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
		function httpRequest($url, $data = null)
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
			if (!empty($data)) {
				curl_setopt($curl, CURLOPT_POST, 1);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			}
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($curl);
			curl_close($curl);
			return $output;
		}
		$res = httpRequest($url);
		print_r($res);
	}
	public function getaccess_token()
	{
		global $_W, $_GPC;
		$res = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]));
		$appid = $res["appid"];
		$secret = $res["appsecret"];
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret . '';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$data = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($data, true);
		return $data["access_token"];
	}
	public function request_post($url, $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$tmpInfo = curl_exec($ch);
		$error = curl_errno($ch);
		curl_close($ch);
		if ($error) {
			return false;
		} else {
			return $tmpInfo;
		}
	}
	public function doPageGetwxCode()
	{
		global $_W, $_GPC;
		$access_token = $this->getaccess_token();
		$scene = $_GPC["scene"];
		$page = $_GPC["page"];
		$width = $_GPC["width"] ? $_GPC["width"] : 430;
		$auto_color = $_GPC["auto_color"] ? $_GPC["auto_color"] : false;
		$line_color = $_GPC["line_color"] ? $_GPC["line_color"] : "{\"r\":\"0\",\"g\":\"0\",\"b\":\"0\"}";
		$is_hyaline = $_GPC["is_hyaline"] ? $_GPC["is_hyaline"] : false;
		$gid = intval($_GPC["gid"]);
		$uniacid = $_W["uniacid"];
		if ($gid > 0) {
		}
		$url = "https://api.weixin.qq.com/wxa/getwxacode?access_token=" . $access_token;
		$data["path"] = $page;
		$data["width"] = $width;
		$json_data = json_encode($data);
		if (!empty($goods["code_img"])) {
			$return = $goods["code_img"];
		} else {
			$return = $this->request_post($url, $json_data);
			$res = pdo_update("yzcj_sun_goods", array("code_img" => $return), array("uniacid" => $uniacid, "gid" => $gid));
		}
		$imgname = time() . rand(10000, 99999) . ".jpg";
		file_put_contents("../attachment/" . $imgname, $return);
		echo json_encode($imgname);
	}
	public function doPageDelwxCode()
	{
		global $_W, $_GPC;
		$imgurl = $_GPC["imgurl"];
		$filename = "../attachment/" . $imgurl;
		if (file_exists($filename)) {
			$info = "删除成功";
			unlink($filename);
		} else {
			$info = "没找到:" . $filename;
		}
		echo $info;
	}
	public function doPageActiveMessage()
	{
		global $_W, $_GPC;
		$res = pdo_getall("yzcj_sun_userformid", array("uniacid" => $_W["uniacid"], "gid" => $_GPC["gid"], "state" => 2));
		$template_id = pdo_get("yzcj_sun_sms", array("uniacid" => $_W["uniacid"]), "tid1")["tid1"];
		foreach ($res as $key => $value) {
			$result = $this->SendMessage($value["openid"], $value["gid"], $template_id, $_GPC["page"], $value["id"], $_GPC["access_token"]);
		}
	}
	public function https_request($url, $data = null)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		if (!empty($data)) {
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
		return $output;
	}
	public function SendMessage($openid, $gid, $template_id, $page, $form_id, $id, $access_token)
	{
		global $_W, $_GPC;
		$gname = pdo_get("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "gid" => $gid), "gname")["gname"];
		$data_arr = array("thing4" => array("value" => $gname, "color" => "black"), "thing5" => array("value" => "您参与的抽奖正在开奖，点击查看详情", "color" => "red"));
		$post_data = array("touser" => $openid, "template_id" => $template_id, "page" => $page, "data" => $data_arr);
		$url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=" . $access_token;
		$json_data = json_encode($post_data);
		$res = $this->https_request($url, urldecode($json_data));
		$res = json_decode($res, true);
		if ($res["errcode"] == 0 && $res["errcode"] == "ok") {
			pdo_delete("yzcj_sun_dingyue", array("openid" => $openid, "tpl_id" => $template_id));
			echo "发送成功！<br/>";
		}
	}
	public function doPageAutoMessage()
	{
		global $_W, $_GPC;
		$res = pdo_getall("yzcj_sun_userformid", array("uniacid" => $_W["uniacid"], "gid" => $_GPC["gid"], "state" => 2));
		$template_id = pdo_get("yzcj_sun_sms", array("uniacid" => $_W["uniacid"]), "tid1")["tid1"];
		foreach ($res as $key => $value) {
			$result = $this->SendMessage($value["openid"], $_GPC["gid"], $template_id, $_GPC["page"], $value["form_id"], $value["id"], $_GPC["access_token"]);
		}
	}
	public function doPageDeliveryMessage()
	{
		global $_W, $_GPC;
		$oid = $_GPC["oid"];
		$where = "where a.uniacid=" . $_W["uniacid"] . " and a.oid='{$oid}'";
		$sql = "select a.*,b.gname,c.* from " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join " . tablename("yzcj_sun_address") . "c on c.adid=a.adid " . $where;
		$result = pdo_fetch($sql);
		$form = pdo_get("yzcj_sun_userformid", array("uniacid" => $_W["uniacid"], "gid" => $result["gid"], "state" => 1));
		$template_id = pdo_get("yzcj_sun_sms", array("uniacid" => $_W["uniacid"]), "tid2")["tid2"];
		$data_arr = array("character_string1" => array("value" => $result["orderNum"], "color" => "black"), "thing2" => array("value" => $result["gname"], "color" => "red"), "thing3" => array("value" => "礼物已发货，请您注意查收！", "color" => "red"));
		$post_data = array("touser" => $form["openid"], "template_id" => $template_id, "page" => $_GPC["page"], "data" => $data_arr);
		$url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=" . $_GPC["access_token"];
		$json_data = json_encode($post_data);
		$res = $this->https_request($url, urldecode($json_data));
		$res = json_decode($res, true);
		if ($res["errcode"] == 0 && $res["errcode"] == "ok") {
			pdo_delete("yzcj_sun_dingyue", array("openid" => $form["openid"], "tpl_id" => $template_id));
			echo "发送成功！<br/>";
		}
	}
	function getdistance($lng1, $lat1, $lng2, $lat2)
	{
		$radLat1 = deg2rad($lat1);
		$radLat2 = deg2rad($lat2);
		$radLng1 = deg2rad($lng1);
		$radLng2 = deg2rad($lng2);
		$a = $radLat1 - $radLat2;
		$b = $radLng1 - $radLng2;
		$s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
		return $s;
	}
	public function doPageShowCircle()
	{
		global $_W, $_GPC;
		$openid = $_GPC["openid"];
		$index = $_GPC["index"];
		$typeId = $_GPC["type"];
		if ($_GPC["longitude_dq"] != undefined) {
			$longitude_dq = $_GPC["longitude_dq"];
			$latitude_dq = $_GPC["latitude_dq"];
		}
		$where = "where a.uniacid=" . $_W["uniacid"] . " and a.status=2";
		if ($typeId != null && $typeId != undefined) {
			if ($typeId != 0) {
				$where .= " and a.type =" . $typeId;
			}
		}
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$type = pdo_getall("yzcj_sun_selectedtype", array("uniacid" => $_W["uniacid"]));
		$sql = "select a.*,b.name,b.img as avatarUrl,c.tname from " . tablename("yzcj_sun_circle") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid left join " . tablename("yzcj_sun_selectedtype") . " c on c.id = a.type " . $where . " ORDER BY a.time desc";
		$res = pdo_fetchall($sql);
		$love = [];
		$con = [];
		$time = [];
		$distance = [];
		foreach ($res as $key => $value) {
			$id = $value["id"];
			$lovenum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_praise") . " where cid=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
			$res[$key]["lovenum"] = $lovenum;
			$love[] = $res[$key]["lovenum"];
			$lovestate = pdo_get("yzcj_sun_praise", array("uid" => $uid, "cid" => $id, "uniacid" => $_W["uniacid"]));
			if ($lovestate) {
				$res[$key]["lovestate"] = true;
			} else {
				$res[$key]["lovestate"] = false;
			}
			$res[$key]["img"] = explode(",", $res[$key]["img"]);
			$conmmentnum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_content") . " where cid=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
			$res[$key]["conmmentnum"] = $conmmentnum;
			$con[] = $res[$key]["conmmentnum"];
			$res[$key]["shijian"] = strtotime($value["time"]);
			$time[] = $res[$key]["shijian"];
			if ($_GPC["longitude_dq"] != undefined) {
				$res[$key]["juli"] = round($this->getdistance($longitude_dq, $latitude_dq, $value["longitude"], $value["latitude"]) / 1000, 1);
				$distance[] = $res[$key]["juli"];
			}
		}
		if ($index == 0) {
			array_multisort($con, SORT_DESC, $love, SORT_DESC, $res);
		} elseif ($index == 1) {
			array_multisort($time, SORT_DESC, $res);
		} elseif ($index == 2) {
			if ($_GPC["longitude_dq"] != undefined) {
				array_multisort($distance, SORT_ASC, $res);
			}
		}
		$info = array("res" => $res, "type" => $type);
		echo json_encode($info);
	}
	public function doPageShowMyCircle()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$where = "where a.uniacid=" . $_W["uniacid"] . " and a.status=2 and a.uid={$uid}";
		$sql = "select a.*,b.name,b.img as avatarUrl,c.tname from " . tablename("yzcj_sun_circle") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid left join " . tablename("yzcj_sun_selectedtype") . " c on c.id = a.type " . $where . " ORDER BY a.time desc";
		$res = pdo_fetchall($sql);
		$where1 = "where a.uniacid=" . $_W["uniacid"] . " and a.status=1 and a.uid={$uid}";
		$sql1 = "select a.*,b.name,b.img as avatarUrl from " . tablename("yzcj_sun_circle") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid " . $where1 . " ORDER BY a.time desc";
		$res1 = pdo_fetchall($sql1);
		foreach ($res as $key => $value) {
			$id = $value["id"];
			$lovenum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_praise") . " where cid=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
			$res[$key]["lovenum"] = $lovenum;
			$lovestate = pdo_get("yzcj_sun_praise", array("uid" => $uid, "cid" => $id, "uniacid" => $_W["uniacid"]));
			if ($lovestate) {
				$res[$key]["lovestate"] = true;
			} else {
				$res[$key]["lovestate"] = false;
			}
			$res[$key]["img"] = explode(",", $res[$key]["img"]);
			$conmmentnum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_content") . " where cid=" . "'{$id}' and uniacid=" . $_W["uniacid"]);
			$res[$key]["conmmentnum"] = $conmmentnum;
		}
		foreach ($res1 as $key => $value) {
			$id = $value["id"];
			$res1[$key]["img"] = explode(",", $res1[$key]["img"]);
		}
		$info = array("res" => $res, "res1" => $res1);
		echo json_encode($info);
	}
	public function doPageDelParise()
	{
		global $_W, $_GPC;
		$openid = $_GPC["openid"];
		$cid = $_GPC["id"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$lovestate = pdo_get("yzcj_sun_praise", array("uid" => $uid, "cid" => $cid, "uniacid" => $_W["uniacid"]));
		if ($lovestate) {
			$res = pdo_delete("yzcj_sun_praise", array("id" => $lovestate["id"], "uniacid" => $_W["uniacid"]));
		} else {
			$data["uid"] = $uid;
			$data["cid"] = $cid;
			$data["uniacid"] = $_W["uniacid"];
			$res = pdo_insert("yzcj_sun_praise", $data);
		}
		echo $res;
	}
	public function doPageDelCircle()
	{
		global $_W, $_GPC;
		$cid = $_GPC["id"];
		$res = pdo_delete("yzcj_sun_circle", array("id" => $cid, "uniacid" => $_W["uniacid"]));
	}
	public function doPageCircleDetail()
	{
		global $_W, $_GPC;
		$openid = $_GPC["openid"];
		$cid = $_GPC["id"];
		$uid = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$where = "where a.uniacid=" . $_W["uniacid"] . " and a.id='{$cid}'";
		$sql = "select a.*,b.name,b.img as avatarUrl,c.tname from " . tablename("yzcj_sun_circle") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid left join " . tablename("yzcj_sun_selectedtype") . " c on c.id=a.type " . $where;
		$res = pdo_fetch($sql);
		$lovenum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_praise") . " where cid=" . "'{$cid}' and uniacid =" . $_W["uniacid"]);
		$res["lovenum"] = $lovenum;
		$lovestate = pdo_get("yzcj_sun_praise", array("uid" => $uid, "cid" => $cid, "uniacid" => $_W["uniacid"]));
		if ($lovestate) {
			$res["lovestate"] = true;
		} else {
			$res["lovestate"] = false;
		}
		$res["img"] = explode(",", $res["img"]);
		$conmmentnum = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_content") . " where cid=" . "'{$cid}' and uniacid =" . $_W["uniacid"]);
		$res["conmmentnum"] = $conmmentnum;
		$where1 = "where a.uniacid=" . $_W["uniacid"] . " and a.cid='{$cid}'";
		$sql1 = "select a.*,b.name,b.img as avatarUrl from " . tablename("yzcj_sun_content") . "a left join " . tablename("yzcj_sun_user") . "b on b.id=a.uid " . $where1;
		$res1 = pdo_fetchall($sql1);
		$info = array("res" => $res, "res1" => $res1);
		echo json_encode($info);
	}
	public function doPagePutContent()
	{
		global $_W, $_GPC;
		$openid = $_GPC["openid"];
		$data["uid"] = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$data["cid"] = $_GPC["id"];
		$data["content"] = $_GPC["content"];
		$data["uniacid"] = $_W["uniacid"];
		$res = pdo_insert("yzcj_sun_content", $data);
	}
	public function doPageSendCircle()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$data["uid"] = pdo_get("yzcj_sun_user", array("openid" => $openid, "uniacid" => $_W["uniacid"]), "id")["id"];
		$data["content"] = $_GPC["content"];
		$data["type"] = $_GPC["type"];
		if ($_GPC["uname"] != undefined) {
			$data["uname"] = $_GPC["uname"];
		}
		if ($_GPC["uphone"] != undefined) {
			$data["uphone"] = $_GPC["uphone"];
		}
		if ($_GPC["addr"] != undefined) {
			$data["addr"] = $_GPC["addr"];
		}
		$data["latitude"] = $_GPC["latitude"];
		$data["longitude"] = $_GPC["longitude"];
		$data["status"] = pdo_get("yzcj_sun_system", array("uniacid" => $_W["uniacid"]), "is_zx")["is_zx"];
		$data["uniacid"] = $_W["uniacid"];
		$res = pdo_insert("yzcj_sun_circle", $data);
		$id = pdo_insertid();
		echo json_encode($id);
	}
	public function doPageCircleType()
	{
		global $_GPC, $_W;
		$res = pdo_getAll("yzcj_sun_selectedtype", array("uniacid" => $_W["uniacid"]));
		echo json_encode($res);
	}
	public function doPageToupload1()
	{
		global $_GPC, $_W;
		$id = $_GPC["id"];
		$uptypes = array("image/jpg", "image/jpeg", "image/png", "image/pjpeg", "image/gif", "image/bmp", "image/x-png");
		$max_file_size = 2000000;
		$destination_folder = "../attachment/";
		$imgpreview = 1;
		$imgpreviewsize = 1 / 2;
		if (!is_uploaded_file($_FILES["file"]["tmp_name"])) {
			echo "图片不存在!";
			exit;
		}
		$file = $_FILES["file"];
		if ($max_file_size < $file["size"]) {
			echo "文件太大!";
			exit;
		}
		if (!in_array($file["type"], $uptypes)) {
			echo "文件类型不符!" . $file["type"];
			exit;
		}
		if (!file_exists($destination_folder)) {
			mkdir($destination_folder);
		}
		$filename = $file["tmp_name"];
		$image_size = getimagesize($filename);
		$pinfo = pathinfo($file["name"]);
		$ftype = $pinfo["extension"];
		$destination = $destination_folder . str_shuffle(time() . rand(111111, 999999)) . "." . $ftype;
		if (file_exists($destination) && $overwrite != true) {
			echo "同名文件已经存在了";
			exit;
		}
		if (!move_uploaded_file($filename, $destination)) {
			echo "移动文件出错";
			exit;
		}
		$pinfo = pathinfo($destination);
		$fname = $pinfo["basename"];
		$newimg = $fname;
		$img = pdo_getcolumn("yzcj_sun_circle", array("id" => $id, "uniacid" => $_W["uniacid"]), "img");
		if ($img) {
			$data["img"] = $img . "," . $newimg;
		} else {
			$data["img"] = $newimg;
		}
		$res = pdo_update("yzcj_sun_circle", $data, array("id" => $id, "uniacid" => $_W["uniacid"]));
		echo json_encode($res);
		echo $fname;
		@(require_once IA_ROOT . "/framework/function/file.func.php");
		@($filename = $fname);
		@file_remote_upload($filename);
	}
	public function doPageAllGifts()
	{
		global $_GPC, $_W;
		$type = pdo_getall("yzcj_sun_type", array("uniacid" => $_W["uniacid"]));
		$giftsbanner = pdo_get("yzcj_sun_giftsbanner", array("uniacid" => $_W["uniacid"]));
		foreach ($type as $key => $value) {
			$gifts = pdo_getall("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "type" => $value["id"], "status" => 2, "count >" => 0), array("id", "gname", "price", "lottery", "pic"));
			foreach ($gifts as $k => $v) {
				$v["pic"] = explode(",", $v["pic"]);
				$gifts[$k]["img"] = $v["pic"];
			}
			$type[$key]["gifts"] = $gifts;
		}
		$where = "where a.uniacid=" . $_W["uniacid"] . " and b.count>0 and b.status=2";
		$daily = pdo_fetchall("SELECT a.*,b.`id` as gid,b.`gname`,b.`price`,b.`lottery`,b.`pic` FROM " . tablename("yzcj_sun_daily") . " a" . " left join " . tablename("yzcj_sun_gifts") . " b on b.id=a.gid " . $where . " ORDER BY a.id asc");
		foreach ($daily as $key => $value) {
			$value["pic"] = explode(",", $value["pic"]);
			$daily[$key]["img"] = $value["pic"];
		}
		$info = array("type" => $type, "giftsbanner" => $giftsbanner, "daily" => $daily);
		echo json_encode($info);
	}
	public function doPageGiftsDetail()
	{
		global $_GPC, $_W;
		$id = $_GPC["id"];
		$where = "where a.uniacid=" . $_W["uniacid"] . " and id='{$id}'";
		$gifts = pdo_fetch("SELECT a.*,b.`sname` FROM " . tablename("yzcj_sun_gifts") . " a" . " left join " . tablename("yzcj_sun_sponsorship") . " b on b.sid=a.sid " . $where . " ORDER BY a.id asc");
		$gifts["pic"] = explode(",", $gifts["pic"]);
		echo json_encode($gifts);
	}
	public function doPageadminLogin()
	{
		global $_GPC, $_W;
		$openid = $_GPC["openid"];
		$uid = pdo_get("yzcj_sun_user", array("uniacid" => $_W["uniacid"], "openid" => $openid), "id")["id"];
		$sid = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "uid" => $uid, "status" => 2), "sid")["sid"];
		if (empty($sid)) {
			echo 0;
		} else {
			echo json_encode($sid);
		}
	}
	public function doPageAdminIndex()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$waitorder1 = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =2 and b.cid=1 and a.uniacid=" . $_W["uniacid"]);
		$yiorder1 = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =6 and b.cid=1 and a.uniacid=" . $_W["uniacid"]);
		$completeorder1 = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =5 and b.cid=1 and a.uniacid=" . $_W["uniacid"]);
		$waitorder = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =2 and a.uniacid=" . $_W["uniacid"]);
		$yiorder = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =6 and a.uniacid=" . $_W["uniacid"]);
		$completeorder = pdo_fetchcolumn("SELECT count(oid) FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =5 and a.uniacid=" . $_W["uniacid"]);
		$shelves = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_gifts") . " where sid=" . "'{$sid}' and status =2 and uniacid=" . $_W["uniacid"]);
		$noshelves = pdo_fetchcolumn("SELECT count(id) FROM " . tablename("yzcj_sun_gifts") . " where sid=" . "'{$sid}' and status =1 and uniacid=" . $_W["uniacid"]);
		$sponsor = pdo_get("yzcj_sun_sponsorship", array("uniacid" => $_W["uniacid"], "sid" => $sid));
		$info = array("waitorder" => $waitorder, "yiorder" => $yiorder, "completeorder" => $completeorder, "waitorder1" => $waitorder1, "yiorder1" => $yiorder1, "completeorder1" => $completeorder1, "shelves" => $shelves, "noshelves" => $noshelves, "sponsor" => $sponsor);
		echo json_encode($info);
	}
	public function doPageAdminOrder()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$waitorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =2 and a.uniacid=" . $_W["uniacid"] . " ORDER BY a.adid desc");
		$waitorder = $this->sliceArr($waitorder);
		$yiorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =6 and a.uniacid=" . $_W["uniacid"]);
		$yiorder = $this->sliceArr($yiorder);
		$completeorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =5 and a.uniacid=" . $_W["uniacid"]);
		$completeorder = $this->sliceArr($completeorder);
		$info = array("waitorder" => $waitorder, "yiorder" => $yiorder, "completeorder" => $completeorder);
		echo json_encode($info);
	}
	public function doPageAdminOrder1()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$waitorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =2 and b.cid=1 and a.uniacid=" . $_W["uniacid"] . " ORDER BY a.adid desc");
		$waitorder = $this->sliceArr($waitorder);
		$yiorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =6 and b.cid=1 and a.uniacid=" . $_W["uniacid"]);
		$yiorder = $this->sliceArr($yiorder);
		$completeorder = pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =5 and b.cid=1 and a.uniacid=" . $_W["uniacid"]);
		$completeorder = $this->sliceArr($completeorder);
		$info = array("waitorder" => $waitorder, "yiorder" => $yiorder, "completeorder" => $completeorder);
		echo json_encode($info);
	}
	public function doPageOrderdetail()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$order = pdo_fetch("SELECT a.*,a.`status` as statuss,b.*,c.`name`,c.`telNumber`,c.`countyName`,c.`detailAddr`,c.`provinceName`,c.`cityname`,c.`detailInfo`,c.`postalCode`,d.`price` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_address") . "c on c.adid=a.adid left join" . tablename("yzcj_sun_gifts") . "d on d.id=b.giftId where a.oid=" . "'{$oid}' and a.uniacid=" . $_W["uniacid"]);
		$order["code_img"] = '';
		echo json_encode($order);
	}
	public function doPagedelivery()
	{
		global $_GPC, $_W;
		$oid = $_GPC["oid"];
		$data["status"] = 6;
		$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $oid));
	}
	public function doPagedoSdelivery()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$oid = pdo_fetchall("SELECT a.`oid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid left join" . tablename("yzcj_sun_gifts") . "c on c.id=b.giftId where c.sid=" . "'{$sid}' and a.status =2 and a.adid!='' and a.uniacid=" . $_W["uniacid"]);
		if (!empty($oid)) {
			foreach ($oid as $key => $value) {
				$data["status"] = 6;
				$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $value["oid"]));
			}
			echo json_encode($oid);
		} else {
			echo 0;
		}
	}
	public function doPagedoSdelivery1()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$oid = pdo_fetchall("SELECT a.`oid` FROM " . tablename("yzcj_sun_order") . "a left join " . tablename("yzcj_sun_goods") . "b on b.gid=a.gid where b.sid=" . "'{$sid}' and a.status =2 and a.adid!='' and a.uniacid=" . $_W["uniacid"]);
		if (!empty($oid)) {
			foreach ($oid as $key => $value) {
				$data["status"] = 6;
				$res = pdo_update("yzcj_sun_order", $data, array("uniacid" => $_W["uniacid"], "oid" => $value["oid"]));
			}
			echo json_encode($oid);
		} else {
			echo 0;
		}
	}
	public function doPageAdminGift()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$res1 = pdo_fetchall("SELECT * FROM " . tablename("yzcj_sun_gifts") . " where sid=" . "'{$sid}' and status =2 and uniacid=" . $_W["uniacid"]);
		$res2 = pdo_fetchall("SELECT * FROM " . tablename("yzcj_sun_gifts") . " where sid=" . "'{$sid}' and status =1 and uniacid=" . $_W["uniacid"]);
		foreach ($res1 as $key => $value) {
			$res1[$key]["img"] = explode(",", $res1[$key]["pic"]);
			$id = $value["id"];
			$num = pdo_getall("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "giftId" => $id));
			foreach ($num as $k => $v) {
				$sum += $v["count"];
			}
			$res1[$key]["num"] = $sum;
		}
		foreach ($res2 as $key => $value) {
			$res2[$key]["img"] = explode(",", $res2[$key]["pic"]);
			$id = $value["id"];
			$num = pdo_getall("yzcj_sun_goods", array("uniacid" => $_W["uniacid"], "giftId" => $id));
			foreach ($num as $k => $v) {
				$sum += $v["count"];
			}
			$res2[$key]["num"] = $sum;
		}
		$info = array("res1" => $res1, "res2" => $res2);
		echo json_encode($info);
	}
	public function doPageuse()
	{
		global $_GPC, $_W;
		$id = $_GPC["id"];
		$data["status"] = 2;
		$res = pdo_update("yzcj_sun_gifts", $data, array("uniacid" => $_W["uniacid"], "id" => $id));
	}
	public function doPagenoUse()
	{
		global $_GPC, $_W;
		$id = $_GPC["id"];
		$data["status"] = 1;
		$res = pdo_update("yzcj_sun_gifts", $data, array("uniacid" => $_W["uniacid"], "id" => $id));
	}
	public function doPagedoNoUse()
	{
		global $_GPC, $_W;
		$sid = $_GPC["sid"];
		$status = $_GPC["status"];
		if ($status == 1) {
			$data["status"] = 1;
			$res = pdo_update("yzcj_sun_gifts", $data, array("uniacid" => $_W["uniacid"], "sid" => $sid));
		} else {
			$data["status"] = 2;
			$res = pdo_update("yzcj_sun_gifts", $data, array("uniacid" => $_W["uniacid"], "sid" => $sid));
		}
	}
	public function doPageaddNum()
	{
		global $_GPC, $_W;
		$id = $_GPC["id"];
		$count = $_GPC["count"];
		$oldCount = pdo_get("yzcj_sun_gifts", array("uniacid" => $_W["uniacid"], "id" => $id), "count")["count"];
		$data["count"] = $oldCount + $count;
		$res = pdo_update("yzcj_sun_gifts", $data, array("uniacid" => $_W["uniacid"], "id" => $id));
	}
	
	public function doPageDlist(){
 
    	global $_GPC, $_W;
		$info = pdo_get('yzcj_sun_sms',array('uniacid'=>$_W['uniacid']));
		$model['tid1'] =$info['tid1'];
		$model['tid2'] = $info['tid2'];


		$arr = ['tid1' => '开奖结果通知', 'tid2' => '订单发货提醒'];
		$new_list = [];
		foreach ($model as $k => $val) {
		$new_arr['title'] = $arr[$k];
		$new_arr['tpl_name'] = $k;
		$new_arr['tpl_id'] = $val;
		$new_list[] = $new_arr;
		}
		foreach ($new_list as $key => $value) {
            $new_list[$key]['rec'] = pdo_get('yzcj_sun_dingyue',array('uniacid'=>$_W['uniacid'],'user_id'=>$_GPC['user_id'],'tpl_id'=>$value['tpl_id'],'tpl_name'=>$value['tpl_name']));
              if ($new_list[$key]['rec']) {
              $new_list[$key]['is_dy'] = 1;
              } 
              
        }
		echo  json_encode($new_list);

    }
    
    public function doPageSubscribe(){ 
    	global $_GPC, $_W;
    	$user = pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$_GPC['user_id']));
        $detail=array(
            'uniacid'=>$_W['uniacid'],
            'addtime'=>time(),
            'user_id'=>$_GPC['user_id'],
            'state'=>1,
            'tpl_id'=>$_GPC['tpl_id'],
            'tpl_name'=>$_GPC['tpl_name'],
            'openid'=>$user['openid'],
            
        );
       // echo "<pre>";print_r($detail);die;
         $res=pdo_insert('yzcj_sun_dingyue',$detail);
         success_withimg_json($res);
    }
}