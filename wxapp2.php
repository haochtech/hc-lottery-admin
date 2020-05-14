<?php

defined('IN_IA') or exit('Access Denied');

class yzcj_sunModuleWxapp extends WeModuleWxapp {
    /************************************************首页*****************************************************/
    //获取openid
    public function doPageOpenid(){
        global $_W, $_GPC;
        $res=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $code=$_GPC['code'];
        $appid=$res['appid'];
        $secret=$res['appsecret'];
        $url="https://api.weixin.qq.com/sns/jscode2session?appid=".$appid."&secret=".$secret."&js_code=".$code."&grant_type=authorization_code";
        function httpRequest($url,$data = null){
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            if (!empty($data)){
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($curl);
            curl_close($curl);
            return $output;
        }
        $re=httpRequest($url);
        print_r($re);
    }

    //登录用户信息
    public function doPageLogin(){
        global $_GPC, $_W;
        $openid=$_GPC['openid'];
        $res=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
        if($openid and $openid!='undefined'){
            if($res){
                $user_id=$res['id'];
                $data['openid']=$_GPC['openid'];
                $data['img']=$_GPC['img'];
                $data['name']=$this->emoji_encode($_GPC['name']);
                $res = pdo_update('yzcj_sun_user', $data, array('id' =>$user_id,'uniacid'=>$_W['uniacid']));
                $user=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
                echo json_encode($user);
            }else{
                $data['openid']=$_GPC['openid'];
                $data['img']=$_GPC['img'];
                $data['name']=$this->emoji_encode($_GPC['name']);
                $data['uniacid']=$_W['uniacid'];
                $data['time']=date('Y-m-d H:i:s',time());
                $res2=pdo_insert('yzcj_sun_user',$data);
                $user=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
                echo json_encode($user);
            }
        }
    }
    function emoji_encode($nickname){
        $strEncode = '';
        $length = mb_strlen($nickname, 'utf-8');
        for ($i = 0; $i < $length; $i++) {
            $_tmpStr = mb_substr($nickname, $i, 1, 'utf-8');
            if (strlen($_tmpStr) >= 4) {
                $strEncode .= '[[EMOJI:' . rawurlencode($_tmpStr) . ']]';
            } else {
                $strEncode .= $_tmpStr;
            }
        }
        return $strEncode;
    }
    //对emoji表情转反义
function emoji_decode($str){
    $strDecode = preg_replace_callback('|\[\[EMOJI:(.*?)\]\]|', function ($matches) {
        return rawurldecode($matches[1]);
    }, $str);

    return $strDecode;
}


    //判断是否开奖
    public function doPageSetTimeout(){
        global $_GPC, $_W;
        //查询每一个正在抽奖中的项目
        $goods=pdo_getall('yzcj_sun_goods',array('uniacid'=>$_W['uniacid'],'status'=>'2'));
        

        $goods=$this->sliceArr($goods);
        //声明空数组，准备承载数据
        $goodsPro=[];
        $orderAll=[];
        $codeAll=[];
        $orderProYes=[];
        $orderProNo=[];
        $orderFail=[];
        $day=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'is_open_pop')['is_open_pop'];
        if(!$day||$day==0){
            $day=3;
        }
        //收集所有开奖的商品ID
        $garr=[]; 
        // p($goods);
        // var_dump($goods);
        //遍历项目
        foreach ($goods as $key => $value) {
            $gid=$value['gid'];
            
                //按时间开奖
                if($value['condition']==0){
                    // p($value);
                    $nowtime=time();
                    $endtime=strtotime($value['accurate']);
                    //判断开奖时间
                    if($nowtime>=$endtime){

                        $data['status']=4;
                        $data['endtime']=date("Y-m-d",time());
                        //更改抽奖项目状态
                        $res=pdo_update('yzcj_sun_goods', $data, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                        //查看抽奖是否为抽奖码抽奖
                        if($value['state']==4){
                            $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                            if($value['zuid']!=0){
                                if($value['one']==1){
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']-1+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count']-1;
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }else{
                                if($value['one']==1){
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count'];
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }
                            //打乱数组
                            // shuffle($code);
                            // array_push($codeAll,$code);
                            $garr[] = $value['gid'];
                        }//组团开奖
                        else if($value['state']==3){
                            // shuffle($order);
                            //一二三等奖
                            if($value['one']==1){
                                $ZorderPro=[];
                                //指定人开奖
                                if($value['zuid']!=0){
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    // $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    // shuffle($ZorderPro);
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                    shuffle($order);
                                    $orderProYes1=[];
                                    $orderProNo1=[];

                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$zcount){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$key]['oid']){
                                                            array_push($orderProYes1,$order[$key]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes1,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']-1+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                                }else{
                                    $count=$value['onenum']+$value['twonum']+$value['threenum'];
                                    // p($count);
                                    $orderProYes1=[];
                                    $orderProNo=[];
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$count){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$ke]['oid']){
                                                            array_push($orderProYes1,$order[$ke]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes1,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                

                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    //未中奖
                                    // foreach ($orderProNo as $key => $value) {

                                        $data4['status']=4;
                                        $data4['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                                    // }
                                }
                            }else{
                                $ZorderPro=[];
                                //如果有指定中奖人的话
                                if($value['zuid']!=0){
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    shuffle($ZorderPro);

                                    // p($ZorderPro);
                                    $zcount=$value['count']-1;
                                    $orderProYes1=array_slice($ZorderPro,0,$zcount);
                                    $orderProNo1=array_slice($ZorderPro,$zcount);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                    }
                                }else{
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    // p($order);
                                    //筛选
                                    // $orderProYes=[];
                                    $orderProYes1=array_slice($order,0,$value['count']);
                                    $orderProNo1=array_slice($order,$value['count']);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                    }
                                }
                            }
                            $garr[] = $value['gid'];

                        }

                        else{
                            // 获取购买了此项目的用户
                            $sql="SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid`,b.`one`,b.`onename`,b.`onenum`,b.`twoname`,b.`twonum`,b.`threename`,b.`threenum` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where a.uniacid=".$_W['uniacid']." and a.status='1' and a.gid= '$gid' and a.uniacid=".$_W['uniacid'];
                            $order = pdo_fetchall($sql);
                            if(!empty($order)){
                                //查询参与人数
                                $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid='$gid' and uniacid=".$_W['uniacid']);
                                //当抽奖类型为红包的时候，如果订单数量不够要退钱
                                if($value['cid']==2&&$total<$value['count']){
                                    $count=$value['count']-$total;
                                    $money=$value['gname']*$count;
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                //当抽奖类型为礼物的时候，订单数量不够，要退回去礼物的钱
                                else if($value['cid']==3&&$total<$value['count']){
                                    $count=$value['count']-$total;
                                    //查询礼物单价
                                    $price=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$value['giftId']),'price')['price'];
                                    $money=$price*$count;
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                array_push($orderAll,$order);
                                $garr[] = $value['gid'];
                            }else{
                                //如果没有人购买的话,红包~
                                if($value['cid']==2){
                                    $money=$value['gname']*$value['count'];
                                    //先判断发起用户是不是赞助商用户
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_getall('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['0']['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                //如果没有人购买的话,礼物~
                                else if($value['cid']==3){
                                    //查询礼物单价
                                    $price=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$value['giftId']),'price')['price'];
                                    $money=$price*$value['count'];
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }
                        
                    }
                //按人数开奖
                }else if($value['condition']==1){
                    //查看抽奖人数
                    $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
                    //转换时间
                    $selftime=strtotime($value['selftime']);
                    $endtime=$selftime+$day*24*60*60;
                    $nowtime=time();
                    //按人数开奖
                    if($total>=$value['accurate']){
                        $data['status']=4;
                        $data['endtime']=date("Y-m-d",time());
                        //更改抽奖项目状态
                        $res=pdo_update('yzcj_sun_goods', $data, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                        //抽奖码开奖
                        if($value['state']==4){
                            $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                            if($value['zuid']!=0){
                                if($value['one']==1){
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']-1+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count']-1;
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }else{
                                if($value['one']==1){
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count'];
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }
                            //打乱数组
                            // shuffle($code);
                            // array_push($codeAll,$code);
                            $garr[] = $value['gid'];
                        }
                        //组团开奖
                        else if($value['state']==3){
                            // shuffle($order);
                            //一二三等奖
                            if($value['one']==1){
                                $ZorderPro=[];
                                //指定人开奖
                                if($value['zuid']!=0){
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    // $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    // shuffle($ZorderPro);
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                    shuffle($order);
                                    $orderProYes1=[];
                                    $orderProNo1=[];

                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$zcount){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$key]['oid']){
                                                            array_push($orderProYes1,$order[$key]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes1,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']-1+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                                }else{
                                    $count=$value['onenum']+$value['twonum']+$value['threenum'];
                                    // p($count);
                                    $orderProYes1=[];
                                    $orderProNo=[];
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$count){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$ke]['oid']){
                                                            array_push($orderProYes1,$order[$ke]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes1,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                

                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    //未中奖
                                    // foreach ($orderProNo as $key => $value) {

                                        $data4['status']=4;
                                        $data4['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                                    // }
                                }
                            }else{
                                $ZorderPro=[];
                                //如果有指定中奖人的话
                                if($value['zuid']!=0){
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    shuffle($ZorderPro);

                                    // p($ZorderPro);
                                    $zcount=$value['count']-1;
                                    $orderProYes1=array_slice($ZorderPro,0,$zcount);
                                    $orderProNo1=array_slice($ZorderPro,$zcount);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                    }
                                }else{
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    // p($order);
                                    //筛选
                                    // $orderProYes=[];
                                    $orderProYes1=array_slice($order,0,$value['count']);
                                    $orderProNo1=array_slice($order,$value['count']);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                    }
                                }
                            }
                            $garr[] = $value['gid'];

                        }
                        else{
                            // 获取购买了此项目的用户
                            $sql="SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid`,b.`one`,b.`onename`,b.`onenum`,b.`twoname`,b.`twonum`,b.`threename`,b.`threenum` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid "."where a.uniacid=".$_W['uniacid']." and a.status="."'1' and a.gid= '$gid'";
                            $order = pdo_fetchall($sql);
                            if(!empty($order)){
                                array_push($orderAll,$order);
                                $garr[] = $value['gid'];
                            }
                        }
                        

                    }else if($nowtime>=$endtime){
                        //当时间超过三天后，没有满足条件也自动开奖
                        $data['status']=4;
                        $data['endtime']=date("Y-m-d",time());
                        //更改抽奖项目状态
                        $res=pdo_update('yzcj_sun_goods', $data, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                        if($value['state']==4){
                            $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                            if($value['zuid']!=0){
                                if($value['one']==1){
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']-1+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count']-1;
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }else{
                                if($value['one']==1){
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2,'one'=>1), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['onenum']+$value['twonum']+$value['threenum'];
                                    shuffle($code);

                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$key]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    
                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes,$value['onenum']+$value['twonum']);
                                    }
                                    if(!empty($one)){
                                        foreach ($one as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $k => $v) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $data3['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }else{
                                    // $res=pdo_update('yzcj_sun_order', array('status'=>2), array('uid' =>$value['zuid'],'gid' =>$value['gid'],'uniacid'=>$_W['uniacid']));
                                    $orderProYes=[];
                                    $zcount=$value['count'];
                                    foreach ($code as $k => $v) {
                                        if($k==0){
                                            array_push($orderProYes,$v);
                                            unset($code[$k]);
                                        }else{
                                            foreach ($orderProYes as $ke => $val) {
                                                if($code[$k]['invuid']==$val['invuid']){
                                                    unset($code[$k]);
                                                }
                                            }
                                            foreach ($orderProYes as $ke => $val) {
                                                if(count($orderProYes)<$zcount){
                                                    if($code[$k]['invuid']!=$val['invuid']){
                                                        if($code[$k]){
                                                            array_push($orderProYes,$code[$k]);
                                                            unset($code[$k]);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $orderProNo=$code;
                                    //随机中奖
                                    foreach ($orderProYes as $k => $v) {
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                    //未中奖
                                    foreach ($orderProNo as $k => $v) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$v['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$v['invuid']));
                                    }
                                }
                            }
                            //打乱数组
                            // shuffle($code);
                            // array_push($codeAll,$code);
                            $garr[] = $value['gid'];
                        }
                        //组团开奖
                        else if($value['state']==3){
                            // shuffle($order);
                            //一二三等奖
                            if($value['one']==1){
                                $ZorderPro=[];
                                //指定人开奖
                                if($value['zuid']!=0){
                                    $zcount=$value['onenum']-1+$value['twonum']+$value['threenum'];
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    // $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    // shuffle($ZorderPro);
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                    shuffle($order);
                                    $orderProYes1=[];
                                    $orderProNo1=[];

                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$zcount){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$key]['oid']){
                                                            array_push($orderProYes1,$order[$key]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$key]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>1){
                                        $one=array_slice($orderProYes1,0,$value['onenum']-1);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum']-1,$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']-1+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }
                                    }
                                    //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                                }else{
                                    $count=$value['onenum']+$value['twonum']+$value['threenum'];
                                    // p($count);
                                    $orderProYes1=[];
                                    $orderProNo=[];
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    foreach ($order as $ke => $val) {

                                        if(count($orderProYes1)<$count){
                                        
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                    $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                    if(!empty($orderProYes1)){
                                                        foreach ($orderProYes1 as $k => $v) {
                                                            if($v['oid']!=$invorder['oid']){
                                                                array_push($orderProYes1,$invorder);
                                                            }
                                                        }
                                                    }else{
                                                        array_push($orderProYes1,$invorder);
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }else{
                                                if(!empty($orderProYes1)){
                                                    foreach ($orderProYes1 as $k => $v) {
                                                        if($v['oid']!=$order[$ke]['oid']){
                                                            array_push($orderProYes1,$order[$ke]);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes1,$order[$ke]);
                                                }
                                            }
                                        }

                                        $orderProYes1=$this->array_unique_fb($orderProYes1);
                                    }

                                    //中奖处理
                                    if($value['onenum']>0){
                                        $one=array_slice($orderProYes1,0,$value['onenum']);
                                    }
                                    if($value['twonum']>0){
                                        $two=array_slice($orderProYes1,$value['onenum'],$value['twonum']);
                                    }
                                    if($value['threenum']>0){
                                        $three=array_slice($orderProYes1,$value['onenum']+$value['twonum']);
                                    }

                                    if(!empty($one)){
                                        foreach ($one as $ke => $val) {
                                            // p($value);
                                            $data3['status']=2;
                                            $data3['one']=1;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($two)){
                                        foreach ($two as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=2;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                

                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    if(!empty($three)){
                                        foreach ($three as $ke => $val) {
                                            $data3['status']=2;
                                            $data3['one']=3;
                                            $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$val['gid']),array('invuid'));
                                            //是否组团
                                            if($invuid){  
                                                $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$val['gid']),array('count(id) as count'));
                                                //判断是否组团成功
                                                if($isgroup['count']>=$value['group']){
                                                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$val['gid'],'invuid'=>$invuid['invuid']));
                                                    foreach ($group as $k => $v) {
                                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                    }
                                                }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));

                                                }
                                            }else{
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                                
                                            }
                                            // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                        }
                                    }
                                    //未中奖
                                    // foreach ($orderProNo as $key => $value) {

                                        $data4['status']=4;
                                        $data4['one']=0;
                                        $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                                    // }
                                }
                            }else{
                                $ZorderPro=[];
                                //如果有指定中奖人的话
                                if($value['zuid']!=0){
                                    //判断指定中奖人是否组团
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['zuid'],'gid'=>$gid),array('invuid'));
                                    if($invuid){
                                        //判断是否组团成功
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                        if($isgroup['count']>=$value['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));
                                        }

                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',array('status'=>2), array('gid' =>$gid,'uid'=>$value['zuid'],'uniacid'=>$_W['uniacid']));

                                    }
                                    $ZorderPro=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid !='=>$value['zuid'],'status'=>1));
                                    shuffle($ZorderPro);

                                    // p($ZorderPro);
                                    $zcount=$value['count']-1;
                                    $orderProYes1=array_slice($ZorderPro,0,$zcount);
                                    $orderProNo1=array_slice($ZorderPro,$zcount);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                    }
                                }else{
                                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));
                                    shuffle($order);
                                    // p($order);
                                    //筛选
                                    // $orderProYes=[];
                                    $orderProYes1=array_slice($order,0,$value['count']);
                                    $orderProNo1=array_slice($order,$value['count']);

                                    //随机中奖
                                    foreach ($orderProYes1 as $ke => $val) {
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$val['uid'],'gid'=>$gid),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$gid),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$value['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                    //未中奖
                                    foreach ($orderProNo1 as $ke => $val) {
                                        // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                        $data3['status']=4;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                    }
                                }
                            }
                            $garr[] = $value['gid'];
                            
                        }
                        else{
                            // 获取购买了此项目的用户
                        
                            $sql="SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid`,b.`one`,b.`onename`,b.`onenum`,b.`twoname`,b.`twonum`,b.`threename`,b.`threenum` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid "."where a.uniacid=".$_W['uniacid']." and a.status="."'1' and a.gid= '$gid'";
                            $order = pdo_fetchall($sql);
                            if(!empty($order)){
                                //查询参与人数
                                $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
                                if($value['cid']==2&&$total<$value['count']){
                                    $count=$value['count']-$total;
                                    $money=$value['gname']*$count;
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_getall('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['0']['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['0']['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                //当抽奖类型为礼物的时候，订单数量不够，要退回去礼物的钱
                                else if($value['cid']==3&&$total<$value['count']){
                                    $count=$value['count']-$total;
                                    //查询礼物单价
                                    $price=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$value['giftId']),'price')['price'];
                                    $money=$price*$count;
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                array_push($orderAll,$order);
                                $garr[] = $value['gid'];
                            }else{
                                //如果没有人购买的话
                                if($value['cid']==2){
                                    $money=$value['gname']*$value['count'];
                                    //先判断发起用户是不是赞助商用户
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_getall('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['0']['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['0']['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                                //如果没有人购买的话,礼物~
                                else if($value['cid']==3){
                                    //查询礼物单价
                                    $price=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$value['giftId']),'price')['price'];
                                    $money=$price*$value['count'];
                                    $sid=$value['sid'];
                                    if(!empty($sid)){
                                        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['uid'];
                                    }else{
                                        $uid=$value['uid'];
                                    }
                                    //退钱
                                    $usermoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
                                    $nowmoney=$usermoney+$money;
                                    $data1['money']=$nowmoney;
                                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }
                        
                    }
                }else if($value['condition']==2){
                    //转换时间
                    $selftime=strtotime($value['selftime']);
                    $endtime=$selftime+3*24*60*60;
                    $nowtime=time();
                    if($nowtime>=$endtime){
                        //当时间超过三天后，没有手动开奖的话，就失效
                        $data['status']=5;
                        $data['endtime']=date("Y-m-d",time());
                        //更改抽奖项目状态
                        $res=pdo_update('yzcj_sun_goods', $data, array('uniacid'=>$_W['uniacid'],'gid' =>$value['gid']));
                        // 获取购买了此项目的用户
                        $sql="SELECT a.*,b.`count`,b.`cid`,b.`gname`,b.`zuid` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid "."where a.uniacid=".$_W['uniacid']." and a.status="."'1' and a.gid= '$gid'";
                        $order = pdo_fetchall($sql);
                        if(!empty($order)){
                            array_push($orderFail,$order);
                            // $garr[] = $value['gid'];
                        }else{
                            //如果没有人购买的话
                            if($value['cid']==2){
                                $money=$value['gname']*$value['count'];
                                //先判断发起用户是不是赞助商用户
                                $sid=$value['sid'];
                                if(!empty($sid)){
                                    $uid=pdo_getall('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$value['sid']),'uid')['0']['uid'];
                                }else{
                                    $uid=$value['uid'];
                                }
                                //退钱
                                $usermoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['0']['money'];
                                $nowmoney=$usermoney+$money;
                                $data1['money']=$nowmoney;
                                $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                            }
                        }
                    }
                }
        };
        //失效处理
        foreach ($orderFail as $key => $value) {
            foreach ($value as $k => $v) {
                $data1['status']=3;
                $result5=pdo_update('yzcj_sun_order',$data1, array('uniacid'=>$_W['uniacid'],'oid' =>$v['oid']));
            }
        }
        $ZZorderPro=[];
        $ZZorderPro1=[];
        $ZorderPro=[];
        $ZorderPro1=[];

        $orderProYes2=[];
        $orderProNo2=[];
        // p($orderAll);
        // die;
        //筛选出中奖人名单
        foreach ($orderAll as $k => $v) {
            //打乱数组
            shuffle($v);
            
            //遍历打乱后的数组，取出中奖人名单
            foreach ($v as $ke => $val) {
                // p($val);
                //非一二三等奖
                if($val['one']==2){
                    if($val['zuid']!=0){
                        $zcount=$val['count']-1;
                        if($val['zuid']==$val['uid']){
                            array_push($ZZorderPro, $val);
                        }else{
                            array_push($ZorderPro, $val);
                        }
                    }else if($val['zuid']==0){
                        //中奖人筛选
                        array_push($orderProYes, array_slice($v,0,$val['count']));
                        // p($orderProYes);
                        //获取未中奖的人
                        array_push($orderProNo, array_slice($v,$val['count']));
                    }
                } //一二三等奖
                else{
                    if($val['zuid']!=0){
                        // $zcount=$val['onenum']-1;
                        if($val['zuid']==$val['uid']){
                            // array_push($ZZorderPro1, $val);
                            // p($val);
                            $data3['status']=2;
                            $data3['one']=1;
                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$val['oid'],'uniacid'=>$_W['uniacid']));
                        }else{
                            // p($v);
                            array_push($ZorderPro1, $val);
                        }
                    }else if($val['zuid']==0){
                        //中奖人筛选
                        // array_push($orderProYes2, array_slice($v,0,$val['count']));
                        
                        //中奖处理
                        if($val['onenum']>0){
                            $one=array_slice($v,0,$val['onenum']);
                        }
                        if($val['twonum']>0){
                            $two=array_slice($v,$val['onenum'],$val['twonum']);
                        }
                        if($val['threenum']>0){
                            $three=array_slice($v,$val['onenum']+$val['twonum'],$val['threenum']);
                        }
                        //获取未中奖的人
                        // array_push($orderProNo2, array_slice($v,$val['onenum']+$val['twonum']+$val['threenum']));
                        $orderProNo2=array_slice($v,$val['onenum']+$val['twonum']+$val['threenum']);
                    }
                }
                
            }
        }
        
        // p($ZorderPro1);
        //调用去重方法
        // $one=$this->array_unique_fb($one);
        // $two=$this->array_unique_fb($two);
        // $three=$this->array_unique_fb($three);
        // $orderProNo2=$this->array_unique_fb($orderProNo2);
        // die;
        foreach ($ZorderPro1 as $key => $value) {
            //中奖处理
            if($value['onenum']>1){
                $one=array_slice($ZorderPro1,0,$value['onenum']-1);
            }
            if($value['twonum']>0){
                $two=array_slice($ZorderPro1,$value['onenum']-1,$value['twonum']);
            }
            if($value['threenum']>0){
                $three=array_slice($ZorderPro1,$value['onenum']-1+$value['twonum'],$value['threenum']);
            }
            //获取未中奖的人
            // array_push($orderProNo2, array_slice($v,$val['onenum']-1+$val['twonum']+$val['threenum']));
            $orderProNo2=array_slice($ZorderPro1,$value['onenum']-1+$value['twonum']+$value['threenum']);
        }
        // p($one);
        // p($two);
        // p($three);
        // p($orderProNo2);
        // die;
                            
        if(!empty($one)){
            foreach ($one as $k => $v) {
                $data3['status']=2;
                $data3['one']=1;
                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
            }
        }
        if(!empty($two)){
            foreach ($two as $k => $v) {
                $data3['status']=2;
                $data3['one']=2;
                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
            }
        }
        if(!empty($three)){
            foreach ($three as $k => $v) {
                $data3['status']=2;
                $data3['one']=3;
                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
            }
        }
        //未中奖
        foreach ($orderProNo2 as $k => $v) {
            $data3['status']=4;
            $data3['one']=0;
            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
        }
        //调用去重方法
        $res=$this->array_unique_fb($orderProYes);
        $res1=$this->array_unique_fb($orderProNo);
        //指定中奖更改
        foreach ($ZZorderPro as $key => $value) {
            if($value['cid']=='2'){
                $money=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$value['uid']),'money')['0']['money'];
                $data3['money']=$money+$value['gname'];
                $result1=pdo_update('yzcj_sun_user', $data3, array('id' =>$value['uid'],'uniacid'=>$_W['uniacid']));
            }
            $data4['status']=2;
            $result=pdo_update('yzcj_sun_order', $data4, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
        }
        //指定开奖后，继续判断是否还有人可以中奖
        $ZorderProYes=[];
        $ZorderProNo=[];
        //指定中奖更改
        foreach ($ZorderPro as $key => $value) {
            $zcount=$value['count']-1;
            //筛选
            $ZorderProYes=array_slice($ZorderPro,0,$zcount);
            $ZorderProNo=array_slice($ZorderPro,$zcount);
        }

        //指定后的随机中奖
        foreach ($ZorderProYes as $key => $value) {
            // p($value);
            if($value['cid']==2){
                $userid=$value['uid'];
                $umoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['0']['money'];
                $nmoney=$umoney+$value['gname'];
                $data2['money']=$nmoney;
                $result1=pdo_update('yzcj_sun_user',$data2, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
            }
            $oid=$value['oid'];
            $data5['status']=2;
            $result2=pdo_update('yzcj_sun_order',$data5, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
        }
        //未中奖
        foreach ($ZorderProNo as $key => $value) {
            $oid=$value['oid'];
            $data5['status']=4;
            $result2=pdo_update('yzcj_sun_order',$data5, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
        }

        //随机中奖更改
        foreach ($res as $x => $y) {
            foreach ($y as $z => $c) {
                if($c['cid']=='2'){
                    $money=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$c['uid']),'money')['0']['money'];
                    $data3['money']=$money+$c['gname'];
                    $result1=pdo_update('yzcj_sun_user', $data3, array('id' =>$c['uid'],'uniacid'=>$_W['uniacid']));
                }
                $data4['status']=2;
                $result=pdo_update('yzcj_sun_order', $data4, array('oid' =>$c['oid'],'uniacid'=>$_W['uniacid']));
            }
        }
        //没中奖更改
        foreach ($res1 as $e => $f) {
            foreach ($f as $g => $h) {
                $data4['status']=4;
                $result=pdo_update('yzcj_sun_order', $data4, array('oid' =>$h['oid'],'uniacid'=>$_W['uniacid']));

            }
        }
        if(!empty($garr)){
            echo json_encode($garr);
        }
        
        
    }
    //三维数组去掉重复值 
    public function array_unique_fb($array3D) {
        $tmp_array = array();
        $new_array = array();
        // 1. 循环出所有的行. ( $val 就是某个行)
        foreach($array3D as $k => $val){
            $hash = md5(json_encode($val));
            if (in_array($hash, $tmp_array)) {
                // '这个行已经有过了';
            }else{
                // 2. 在 foreach 循环的主体中, 把每行数组对象得hash 都赋值到那个临时数组中.
                $tmp_array[] = $hash;
                $new_array[] = $val;
            }
        }
        return ($new_array);
    } 
    //二维数组去重
    public function array_unset_tt($arr, $key)
    {
        //建立一个目标数组
        $res = array();
        foreach ($arr as $value) {
            //查看有没有重复项
            if (isset($res[$value[$key]])) {
                //有：销毁
                unset($value[$key]);
            } else {
                $res[$value[$key]] = $value;
            }
        }
        return $res;
    }


    //手动开奖
    public function doPageDoLottery(){
        global $_GPC, $_W;
        $gid= $_GPC['gid'];
        // $gid= 156;
        //获取抽奖信息
        $goods=pdo_get('yzcj_sun_goods',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
        
        //获取参与了此次抽奖的用户
        $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
        //判断是否有人参与抽奖
        if(!empty($order)){
            $data['status']=4;
            $data['endtime']=date("Y-m-d",time());
            //更改抽奖项目状态
            $res=pdo_update('yzcj_sun_goods', $data, array('gid' =>$gid,'uniacid'=>$_W['uniacid']));
            if($goods['cid']==2){
                //查询参与人数
                $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
                //如果参与人数不到数量，要退钱给发起人
                if($goods['count']>=$total){
                    $count=$goods['count']-$total;
                    $money=$goods['gname']*$count;
                    
                    //先判断发起用户是不是赞助商用户
                    $sid=$goods['sid'];
                    if(!empty($sid)){
                        $uid=pdo_getall('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$goods['sid']),'uid')['0']['uid'];
                    }else{
                        $uid=$goods['uid'];
                    }
                    $usermoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['0']['money'];
                    $nowmoney=$usermoney+$money;
                    $data1['money']=$nowmoney;
                    $result=pdo_update('yzcj_sun_user',$data1, array('id' =>$uid,'uniacid'=>$_W['uniacid']));
                    foreach ($order as $key => $value) {
                        $userid=$value['uid'];
                        $umoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['0']['money'];
                        $nmoney=$umoney+$goods['gname'];
                        $data2['money']=$nmoney;
                        $result1=pdo_update('yzcj_sun_user',$data2, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                        $oid=$value['oid'];
                        $data3['status']=2;
                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    }
                }else{
                    //打乱数组
                    shuffle($order);
                    $ZorderPro=[];
                    if($goods['zuid']!=0){
                        foreach ($order as $key => $value) {
                            if($value['uid']==$goods['zuid']){
                                $userid=$value['uid'];
                                $umoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['0']['money'];
                                $nmoney=$umoney+$goods['gname'];
                                $data2['money']=$nmoney;
                                $result1=pdo_update('yzcj_sun_user',$data2, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                $oid=$value['oid'];
                                $data3['status']=2;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                            }else{
                                array_push($ZorderPro,$value);
                            }
                        }
                        $zcount=$goods['count']-1;
                        //筛选
                        $orderProYes=array_slice($ZorderPro,0,$zcount);
                        $orderProNo=array_slice($ZorderPro,$zcount);
                        //随机中奖
                        foreach ($orderProYes as $key => $value) {
                            $userid=$value['uid'];
                            $umoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['0']['money'];
                            $nmoney=$umoney+$goods['gname'];
                            $data2['money']=$nmoney;
                            $result1=pdo_update('yzcj_sun_user',$data2, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                            $oid=$value['oid'];
                            $data3['status']=2;
                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                        }
                        //未中奖
                        foreach ($orderProNo as $key => $value) {
                            $oid=$value['oid'];
                            $data3['status']=4;
                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                        }
                    }else{
                        //筛选
                        $orderProYes=array_slice($order,0,$goods['count']);
                        $orderProNo=array_slice($order,$goods['count']);
                        //随机中奖
                        foreach ($orderProYes as $key => $value) {
                            $userid=$value['uid'];
                            $umoney=pdo_getall('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['0']['money'];
                            $nmoney=$umoney+$goods['gname'];
                            $data2['money']=$nmoney;
                            $result1=pdo_update('yzcj_sun_user',$data2, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                            $oid=$value['oid'];
                            $data3['status']=2;
                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                        }
                        //未中奖
                        foreach ($orderProNo as $key => $value) {
                            $oid=$value['oid'];
                            $data3['status']=4;
                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                        }
                    }
                }
            }else{
                //组团开奖
                if($goods['state']==3){
                    shuffle($order);
                    //一二三等奖
                    if($goods['one']==1){
                        $ZorderPro=[];
                        //指定人开奖
                        if($goods['zuid']!=0){
                            $zcount=$goods['onenum']-1+$goods['twonum']+$goods['threenum'];

                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                            }
                            $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                            shuffle($order);
                            $orderProYes=[];
                            $orderProNo=[];

                            foreach ($order as $key => $value) {

                                if(count($orderProYes)<$zcount){
                                
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                            $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$invorder['oid']){
                                                        array_push($orderProYes,$invorder);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$invorder);
                                            }
                                        }else{
                                            array_push($orderProYes,$order[$key]);
                                        }
                                    }else{
                                        if(!empty($orderProYes)){
                                            foreach ($orderProYes as $k => $v) {
                                                if($v['oid']!=$order[$key]['oid']){
                                                    array_push($orderProYes,$order[$key]);
                                                }
                                            }
                                        }else{
                                            array_push($orderProYes,$order[$key]);
                                        }
                                    }
                                }

                                $orderProYes=$this->array_unique_fb($orderProYes);
                            }

                            //中奖处理
                            if($goods['onenum']>1){
                                $one=array_slice($orderProYes,0,$goods['onenum']-1);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum']-1,$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']-1+$goods['twonum']);
                            }

                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    // p($value);
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                            }
                            //未中奖
                            $data4['status']=4;
                            $data4['one']=0;
                            $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                        }else{
                            $count=$goods['onenum']+$goods['twonum']+$goods['threenum'];
                            // p($count);
                            $orderProYes=[];
                            $orderProNo=[];

                            foreach ($order as $key => $value) {

                                if(count($orderProYes)<$count){
                                
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                            $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$invorder['oid']){
                                                        array_push($orderProYes,$invorder);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$invorder);
                                            }
                                        }else{
                                            array_push($orderProYes,$order[$key]);
                                        }
                                    }else{
                                        if(!empty($orderProYes)){
                                            foreach ($orderProYes as $k => $v) {
                                                if($v['oid']!=$order[$key]['oid']){
                                                    array_push($orderProYes,$order[$key]);
                                                }
                                            }
                                        }else{
                                            array_push($orderProYes,$order[$key]);
                                        }
                                    }
                                }

                                $orderProYes=$this->array_unique_fb($orderProYes);
                            }

                            //中奖处理
                            if($goods['onenum']>0){
                                $one=array_slice($orderProYes,0,$goods['onenum']);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum'],$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']+$goods['twonum']);
                            }

                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    // p($value);
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                    // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                        }
                                    }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        

                                    }
                                    // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){  
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成功
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            foreach ($group as $k => $v) {
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                        }
                                    }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        
                                    }
                                    // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            //未中奖
                            // foreach ($orderProNo as $key => $value) {

                                $data4['status']=4;
                                $data4['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                            // }
                        }
                    }else{
                        $ZorderPro=[];
                        //如果有指定中奖人的话
                        if($goods['zuid']!=0){
                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $oid=$value['oid'];
                                    // $data3['status']=2;
                                    // $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$goods['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }else{
                                    array_push($ZorderPro,$value);
                                }
                            }
                            $zcount=$goods['count']-1;
                            $orderProYes=array_slice($ZorderPro,0,$zcount);
                            $orderProNo=array_slice($ZorderPro,$zcount);

                            //随机中奖
                            foreach ($orderProYes as $key => $value) {
                                $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                //是否组团
                                if($invuid){ 
                                    $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                    //判断是否组团成团
                                    if($isgroup['count']>=$goods['group']){
                                        $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                        //组团成团，一人中奖，全员中奖
                                        foreach ($group as $k => $v) {
                                            $res=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }else{
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                            }
                        }else{
                            //筛选
                            // $orderProYes=[];
                            $orderProYes=array_slice($order,0,$goods['count']);
                            $orderProNo=array_slice($order,$goods['count']);

                            //随机中奖
                            foreach ($orderProYes as $key => $value) {
                                $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                //是否组团
                                if($invuid){ 
                                    $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                    //判断是否组团成团
                                    if($isgroup['count']>=$goods['group']){
                                        $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                        //组团成团，一人中奖，全员中奖
                                        foreach ($group as $k => $v) {
                                            $res=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }else{
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                            }
                        }
                    }
                }
                //抽奖码开奖
                else if($goods['state']==4){
                    // p($order);
                    $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                    //打乱数组
                    shuffle($code);
                    if($goods['one']==1){
                        $ZorderPro=[];
                        if($goods['zuid']!=0){
                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }else{
                                    array_push($ZorderPro,$value);
                                }
                            }
                            $orderProYes=[];
                            $zcount=$goods['onenum']-1+$goods['twonum']+$goods['threenum'];
                            foreach ($code as $key => $value) {
                                if($key==0){
                                    array_push($orderProYes,$value);
                                    unset($code[$key]);
                                }else{
                                    foreach ($orderProYes as $k => $v) {
                                        if($code[$key]['invuid']==$v['invuid']){
                                            unset($code[$key]);
                                        }
                                    }
                                    foreach ($orderProYes as $k => $v) {
                                        if(count($orderProYes)<$zcount){
                                            if($code[$key]['invuid']!=$v['invuid']){
                                                if($code[$key]){
                                                    array_push($orderProYes,$code[$key]);
                                                    unset($code[$key]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $orderProNo=$code;
                            //中奖处理
                            if($goods['onenum']>1){
                                $one=array_slice($orderProYes,0,$goods['onenum']-1);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum']-1,$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']-1+$goods['twonum']);
                            }
                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }

                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $data3['status']=4;
                                $data3['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                            }
                        }else{
                            $count=$goods['onenum']+$goods['twonum']+$goods['threenum'];
                            // p($count);
                            $orderProYes=[];
                            foreach ($code as $key => $value) {
                                if($key==0){
                                    array_push($orderProYes,$value);
                                    unset($code[$key]);
                                }else{
                                    foreach ($orderProYes as $k => $v) {
                                        if($code[$key]['invuid']==$v['invuid']){
                                            unset($code[$key]);
                                        }
                                    }
                                    foreach ($orderProYes as $k => $v) {
                                        if(count($orderProYes)<$count){
                                            if($code[$key]['invuid']!=$v['invuid']){
                                                if($code[$key]){
                                                    array_push($orderProYes,$code[$key]);
                                                    unset($code[$key]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // p($orderProYes);
                            $orderProNo=$code;
                            //中奖处理
                            if($goods['onenum']>0){
                                $one=array_slice($orderProYes,0,$goods['onenum']);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum'],$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']+$goods['twonum']);
                            }
                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {

                                $data3['status']=4;
                                $data3['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                            }
                        }
                    }else{
                        $ZorderPro=[];
                        //如果有指定中奖人的话
                        if($goods['zuid']!=0){
                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }else{
                                    array_push($ZorderPro,$value);
                                }
                            }
                            $orderProYes=[];
                            $zcount=$goods['count']-1;
                            foreach ($code as $key => $value) {
                                if($key==0){
                                    array_push($orderProYes,$value);
                                    unset($code[$key]);
                                }else{
                                    foreach ($orderProYes as $k => $v) {
                                        if($code[$key]['invuid']==$v['invuid']){
                                            unset($code[$key]);
                                        }
                                    }
                                    foreach ($orderProYes as $k => $v) {
                                        if(count($orderProYes)<$zcount){
                                            if($code[$key]['invuid']!=$v['invuid']){
                                                if($code[$key]){
                                                    array_push($orderProYes,$code[$key]);
                                                    unset($code[$key]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $orderProNo=$code;
                            //随机中奖
                            foreach ($orderProYes as $key => $value) {
                                $data3['status']=2;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                            }
                        }else{
                            //筛选
                            $orderProYes=[];
                            foreach ($code as $key => $value) {
                                if($key==0){
                                    array_push($orderProYes,$value);
                                    unset($code[$key]);
                                }else{
                                    foreach ($orderProYes as $k => $v) {
                                        if($code[$key]['invuid']==$v['invuid']){
                                            unset($code[$key]);
                                        }
                                    }
                                    foreach ($orderProYes as $k => $v) {
                                        if(count($orderProYes)<$goods['count']){
                                            if($code[$key]['invuid']!=$v['invuid']){
                                                if($code[$key]){
                                                    array_push($orderProYes,$code[$key]);
                                                    unset($code[$key]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            // p($orderProYes);
                            // p($code);
                            $orderProNo=$code;
                            //随机中奖
                            foreach ($orderProYes as $key => $value) {

                                $data3['status']=2;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {

                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                            }
                        }
                    }
                    
                }
                //普通开奖
                else{
                    //打乱数组
                    shuffle($order);
                    if($goods['one']==1){
                        $count=$goods['onenum']-1+$goods['twonum']+$goods['threenum'];

                        $ZorderPro=[];
                        if($goods['zuid']!=0){ 
                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }else{
                                    array_push($ZorderPro,$value);
                                }
                            }
                            //中奖处理
                            //筛选
                            $orderProYes=array_slice($ZorderPro,0,$count);
                            $orderProNo=array_slice($ZorderPro,$count);
                            if($goods['onenum']>1){
                                $one=array_slice($orderProYes,0,$goods['onenum']-1);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum']-1,$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']-1+$goods['twonum']);
                            }
                            // p($orderProYes);
                            // p($orderProNo);
                            // die;
                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }

                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $data3['status']=4;
                                $data3['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                            }
                            
                        }else{
                            $count=$goods['onenum']+$goods['twonum']+$goods['threenum'];

                            //筛选
                            $orderProYes=array_slice($order,0,$count);
                            $orderProNo=array_slice($order,$count);
                            // p($orderProYes);
                            // p($orderProNo);
                            // die;
                            //中奖处理
                            if($goods['onenum']>0){
                                $one=array_slice($orderProYes,0,$goods['onenum']);
                            }
                            if($goods['twonum']>0){
                                $two=array_slice($orderProYes,$goods['onenum'],$goods['twonum']);
                            }
                            if($goods['threenum']>0){
                                $three=array_slice($orderProYes,$goods['onenum']+$goods['twonum']);
                            }
                            // p($one);
                            // p($two);
                            // p($three);
                            // die;
                            if(!empty($one)){
                                foreach ($one as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=1;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            if(!empty($two)){
                                foreach ($two as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                            if(!empty($three)){
                                foreach ($three as $key => $value) {
                                    $data3['status']=2;
                                    $data3['one']=3;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }

                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $data3['status']=4;
                                $data3['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                            }
                        }
                    }else{
                        $ZorderPro=[];
                        if($goods['zuid']!=0){
                            foreach ($order as $key => $value) {
                                if($value['uid']==$goods['zuid']){
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }else{
                                    array_push($ZorderPro,$value);
                                }
                            }
                            $zcount=$goods['count']-1;
                            //筛选
                            $orderProYes=array_slice($ZorderPro,0,$zcount);
                            $orderProNo=array_slice($ZorderPro,$zcount);
                            //随机中奖
                            foreach ($orderProYes as $key => $value) {
                                $oid=$value['oid'];
                                $data3['status']=2;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $oid=$value['oid'];
                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                            }
                        }else{

                            //筛选
                            $orderProYes=array_slice($order,0,$goods['count']);
                            $orderProNo=array_slice($order,$goods['count']);
                            //随机中奖
                            foreach ($orderProYes as $key => $value) {
                                $oid=$value['oid'];
                                $data3['status']=2;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                            }
                            //未中奖
                            foreach ($orderProNo as $key => $value) {
                                $oid=$value['oid'];
                                $data3['status']=4;
                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                            }
                        }
                    }
                    
                }
                

            }
        }else{
            //如果没有人购买的话
            echo json_encode(1);
        }
    }
    //首页每日精选
    public function doPageProject(){
        global $_GPC, $_W;
        $openid= $_GPC['openid'];
        $sql1="SELECT oid,gid FROM ".tablename('yzcj_sun_user')."a left join ".tablename('yzcj_sun_order')."b on b.uid=a.id where openid="."'$openid' and a.uniacid=".$_W['uniacid'];
        $state = pdo_fetchall($sql1);

        //每日精选
        $where="where b.status=2 and b.sid!='' and a.uniacid=".$_W['uniacid'];
        $res=pdo_fetchall("SELECT a.*,b.*,c.`sname` FROM ".tablename('yzcj_sun_goodsdaily'). " a"  . " left join " . tablename("yzcj_sun_goods") . " b on b.gid=a.gid left join ".tablename("yzcj_sun_sponsorship")." c on c.sid=b.sid ".$where." ORDER BY a.id asc");
        $res=$this->sliceArr($res);
        //分割图片
        // $sql = "select a.*,b.sname from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$where." ORDER BY a.selftime desc limit 0,5";
        // $res = pdo_fetchall($sql);
        foreach ($res as $key => $value) {
            foreach ($state as $k => $v) {
                if($value['gid']==$v['gid']){
                    $res[$key]['oid']=$v['oid'];
                }
            }
        }
        
        //技术支持
        $support=pdo_getall('yzcj_sun_support',array('uniacid'=>$_W['uniacid']));
        //公告
        $addnews=pdo_get('yzcj_sun_addnews',array('uniacid'=>$_W['uniacid']));
        $whereNews="where a.status=2 and DATE_SUB(CURDATE(), INTERVAL 3 DAY) <= date(a.time) and a.uniacid=".$_W['uniacid'];
        $sqlNews = "select a.*,b.name,c.gname,c.cid from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid left join".tablename('yzcj_sun_goods')."c on c.gid=a.gid ".$whereNews;
        
        $resNews = pdo_fetchall($sqlNews);
        foreach ($resNews as $key => $value) {
            $resNews[$key]['name']=$this->emoji_decode($resNews[$key]['name']);
        };

        
        //广告
        $ad=pdo_getall('yzcj_sun_ad',array('uniacid'=>$_W['uniacid'],'status'=>1,'type'=>1));
        $popup=pdo_get('yzcj_sun_ad',array('uniacid'=>$_W['uniacid'],'status'=>1,'type'=>2));
        //轮播图
        $imgUrls=pdo_getall('yzcj_sun_banner',array('uniacid'=>$_W['uniacid']));
        //过审状态
        // $audit=pdo_get('yzcj_sun_audit',array('uniacid'=>$_W['uniacid']));
        //小程序标题
        $title=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        //赞助商判断
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $sponsor=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'uid'=>$uid));
        $info = array(
            'res' => $res,
            'support' => $support,
            'addnews' => $addnews,
            'resNews' => $resNews,
            'ad' => $ad,
            'imgUrls' => $imgUrls,
            'sponsor' => $sponsor,
            'popup' => $popup,
            'title' => $title
        );
        echo json_encode($info);
    }
    //抽奖项目
    public function doPageLuckyProject(){
        global $_GPC, $_W;
        $openid= $_GPC['openid'];
        $sql1="SELECT oid,gid FROM ".tablename('yzcj_sun_user')."a left join ".tablename('yzcj_sun_order')."b on b.uid=a.id where openid="."'$openid' and a.uniacid=".$_W['uniacid'];
        $state = pdo_fetchall($sql1);

        //自动开奖
        $whereAutomatic="where a.status=2 and a.sid!='' and a.condition=0 and a.uniacid=".$_W['uniacid']."|| a.status=2 and a.sid!='' and a.condition=1 and a.uniacid=".$_W['uniacid'];
        $sqlAutomatic = "select a.*,b.sname from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$whereAutomatic." ORDER BY a.selftime desc";
        $resAutomatic = pdo_fetchall($sqlAutomatic);
        $resAutomatic=$this->sliceArr($resAutomatic);
        foreach ($resAutomatic as $key => $value) {
            foreach ($state as $k => $v) {
                if($value['gid']==$v['gid']){
                    $resAutomatic[$key]['oid']=$v['oid'];
                }
            }
        }
        //手动开奖
        $whereManual="where a.status=2 and a.sid!='' and a.condition=2 and a.uniacid=".$_W['uniacid'];
        $sqlManual = "select a.*,b.sname from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$whereManual." ORDER BY a.selftime desc";
        $resManual = pdo_fetchall($sqlManual);
        $resManual=$this->sliceArr($resManual);
        foreach ($resManual as $key => $value) {
            foreach ($state as $k => $v) {
                if($value['gid']==$v['gid']){
                    $resManual[$key]['oid']=$v['oid'];
                }
            }
        }
        //现场开奖
        $whereScene="where a.status=2 and a.sid!='' and a.condition=3 and a.uniacid=".$_W['uniacid'];
        $sqlScene = "select a.*,b.sname from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$whereScene." ORDER BY a.selftime desc";
        $resScene = pdo_fetchall($sqlScene);
        $resScene=$this->sliceArr($resScene);
        foreach ($resScene as $key => $value) {
            foreach ($state as $k => $v) {
                if($value['gid']==$v['gid']){
                    $resScene[$key]['oid']=$v['oid'];
                }
            }
        }

        //赞助商判断
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $sponsor=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'uid'=>$uid));
        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        $info = array(
            'resAutomatic' => $resAutomatic,
            'resManual' => $resManual,
            'resScene' => $resScene,
            'sponsor' => $sponsor,
            'cjzt' => $cjzt,
        );
        echo json_encode($info);
    }

    //抽奖详情
    public function doPageProDetail(){
        global $_GPC, $_W;
        $openid=$_GPC['openid'];
        $gid=$_GPC['gid'];
        $invuid=$_GPC['invuid'];

        //先判断发起用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_goods',array('gid'=>$gid,'uniacid'=>$_W['uniacid']),'sid')['sid'];

        if(!empty($sid)){
            //打印
            $where="where a.gid='$gid' and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,a.status as astatus,b.* from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$where;
            $res = pdo_fetch($sql);
            $res['code_img']='';
            // $res=$this->sliceArr($res);
        }else{
            //打印
            $where="where a.gid='$gid' and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,a.status as astatus,b.name from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid ".$where;
            $res = pdo_fetch($sql);
            $res['code_img']='';

            // $res=$this->sliceArr($res);
        }
            //获取ID
            $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
            $oid=pdo_get('yzcj_sun_order',array('uid'=>$uid,'gid'=>$gid,'uniacid'=>$_W['uniacid']),'oid')['oid'];

        if(!empty($oid)){
            $res['oid']=$oid;
            if($res['state']==3){
                $isgroup=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'gid'=>$gid));
                if($isgroup){
                    // $group=pdo_getall('yzcj_sun_group',arary("uniacid"=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']));
                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']));
                    foreach ($group as $key => $value) {
                        $groupuser=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$value['uid']),array('img'));
                        $group[$key]['img']=$groupuser['img'];
                    }
                    $count=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']),array('count(id) as count'));
                    $res['grouparr']=$group;
                    $res['groupcount']=$count['count'];
                }
            }
        }else{
            $res['oid']=0;
        }
        //查看是否添加抽奖码
        if($res['state']==4&&!empty($invuid)&&$invuid!=undefined&&$res['codeway']==1){
            // $invuid=$_GPC['invuid'];//邀请人的id
            $usercode=pdo_get("yzcj_sun_code",array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid),array('count(id) as count'));
            $code=pdo_get("yzcj_sun_code",array('uniacid'=>$_W['uniacid'],'gid'=>$gid),array('count(id) as count'));
            $oid=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$invuid),array('oid'));
            $iscode=pdo_get('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$uid,'invuid'=>$invuid));
            if(!$iscode){
                if($code['count']<$res['codenum']){
                    if($usercode['count']<$res['codemost']){
                        //抽奖码表
                        $data6['oid']=$oid['oid'];
                        $data6['uid']=$uid;
                        $data6['invuid']=$invuid;
                        $data6['gid']=$gid;
                        $data6['uniacid']=$_W['uniacid'];
                        $res2=pdo_insert('yzcj_sun_code',$data6);
                    }
                }
            }
        }

        //查询人数
        $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid='$gid' and uniacid=".$_W['uniacid']);
        $res['total']=$total;
        //查询用户头像
        $uidarr=pdo_fetchall("select uid from ".tablename('yzcj_sun_order')." where gid = '$gid' and uniacid=".$_W['uniacid']);
        $img=[];
        $img1=[];
        shuffle($uidarr);

        foreach ($uidarr as $key => $value) {
            if($value['uid']==$uid){
                $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id='$uid' and uniacid=".$_W['uniacid']);
                array_push($img1,$res1);
            }
        }
        foreach ($uidarr as $key => $value) {
            if($value['uid']!=$uid){
                if(count($img)<6){
                    $id=$value['uid'];
                    $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id='$id' and uniacid=".$_W['uniacid']);
                    array_push($img,$res1);
                }
            }
        }
        $res['img']=$img;
        $res['img1']=$img1;
        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        $res['cjzt']=$cjzt;
        //广告
        $ad=pdo_getall('yzcj_sun_ad',array('uniacid'=>$_W['uniacid'],'status'=>1,'type'=>1));
        //打乱数组
        shuffle($ad);
        $ad1=[];
        array_push($ad1,array_slice($ad,0,1));
        //空数组
        $ZorderPro=[];
        if($res['astatus']==2){

            if($res['condition']==1){
            
                if($res['accurate']<=$total){
                    $data['status']=4;
                    $data['endtime']=date("Y-m-d",time());
                    //更改抽奖项目状态
                    $result=pdo_update('yzcj_sun_goods', $data, array('gid' =>$gid,'uniacid'=>$_W['uniacid']));
                    //获取参与了此次抽奖的用户
                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                    //组团开奖
                    if($res['state']==3){
                        shuffle($order);
                        //一二三等奖
                        if($res['one']==1){
                            $ZorderPro=[];
                            //指定人开奖
                            if($res['zuid']!=0){
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];

                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                shuffle($order);
                                $orderProYes=[];
                                $orderProNo=[];
                                
                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$zcount){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }
                                // p($orderProYes);
                                // p($res);
                                // p($res['twonum']);
                                // p($res['threenum']);
                                // die;
                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    // p($res['onenum']-1);
                                    // p($res['twonum']);
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }
                                // p($one);
                                // p($two);
                                // p($three);
                                // die;
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                //未中奖
                                $data4['status']=4;
                                $data4['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                $orderProNo=[];

                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$count){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }

                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            

                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                // p($order); 
                                foreach($order as $key => $value) {

                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        // $data3['status']=2;
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        // p($invuid);
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            // p($res['group']);
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){

                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                // p($group);

                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                // die;
                                $zcount=$res['count']-1;
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                }
                            }else{
                                //筛选
                                // $orderProYes=[];
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                }
                            }
                        }
                    }
                    //抽奖码开奖
                    else if($res['state']==4){
                        // p($order);
                        $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                        //打乱数组
                        shuffle($code);
                        if($res['one']==1){
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$count){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // p($orderProYes);
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['count']-1;
                                // p($zcount);
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;

                                // p($orderProYes);
                                // p($orderProNo);
                                // die;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                //筛选
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$res['count']){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                $orderProNo=$code;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {

                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }
                        
                    }
                    //普通开奖
                    else{
                        //打乱数组
                        shuffle($order);
                        if($res['one']==1){
                            $count=$res['onenum']-1+$res['twonum']+$res['threenum'];

                            $ZorderPro=[];
                            if($res['zuid']!=0){ 
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                //中奖处理
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$count);
                                $orderProNo=array_slice($ZorderPro,$count);
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                                
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];

                                //筛选
                                $orderProYes=array_slice($order,0,$count);
                                $orderProNo=array_slice($order,$count);
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }else{
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        if($res['cid']==2){
                                            $userid=$value['uid'];
                                            $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                            $nmoney=$umoney+$res['gname'];
                                            $data4['money']=$nmoney;
                                            $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                        }
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $zcount=$res['count']-1;
                                //筛选
                                shuffle($ZorderPro);
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }else{

                                //筛选
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }
                        
                    }



                    // if($res['zuid']!=0){
                    //     foreach ($order as $key => $value) {
                    //         if($value['uid']==$res['zuid']){
                    //             if($res['cid']==2){
                    //                 $userid=$value['uid'];
                    //                 $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //                 $nmoney=$umoney+$res['gname'];
                    //                 $data4['money']=$nmoney;
                    //                 $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //             }
                    //             $oid=$value['oid'];
                    //             $data2['status']=2;
                    //             $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //         }else{
                    //             array_push($ZorderPro,$value);
                    //         }
                    //     }
                    //     $zcount=$res['count']-1;
                    //     //筛选
                    //     shuffle($ZorderPro);

                    //     $orderProYes=array_slice($ZorderPro,0,$zcount);
                    //     $orderProNo=array_slice($ZorderPro,$zcount);
                    //     //随机中奖
                    //     foreach ($orderProYes as $key => $value) {
                    //         if($res['cid']==2){
                    //             $userid=$value['uid'];
                    //             $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //             $nmoney=$umoney+$res['gname'];
                    //             $data4['money']=$nmoney;
                    //             $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //         }
                    //         $oid=$value['oid'];
                    //         $data2['status']=2;
                    //         $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    //     //未中奖
                    //     foreach ($orderProNo as $key => $value) {
                    //         $oid=$value['oid'];
                    //         $data3['status']=4;
                    //         $result1=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    // }else{
                    //     //筛选
                    //     // p($order);
                    //     shuffle($order);
                    //     // p($order);
                    //     $orderProYes=array_slice($order,0,$res['count']);
                    //     $orderProNo=array_slice($order,$res['count']);
                    //     //随机中奖
                    //     foreach ($orderProYes as $key => $value) {
                    //         if($res['cid']==2){
                    //             $userid=$value['uid'];
                    //             $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //             $nmoney=$umoney+$res['gname'];
                    //             $data4['money']=$nmoney;
                    //             $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //         }
                    //         $oid=$value['oid'];
                    //         $data2['status']=2;
                    //         $result2=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    //     //未中奖
                    //     foreach ($orderProNo as $key => $value) {
                    //         $oid=$value['oid'];
                    //         $data3['status']=4;
                    //         $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    // }

                    $info = array(
                        'num' => 10002
                    );
                    echo json_encode($info);
                }else{
                    $info = array(
                        'num' => 10001,
                        'res' => $res,
                        'ad' => $ad1
                    );
                    // p($info);
                    echo json_encode($info);
                }
            }else if($res['condition']==0){
                $nowtime=time();
                $endtime=strtotime($res['accurate']);
               
                    //判断开奖时间
                if($nowtime>=$endtime){
                    $data['status']=4;
                    $data['endtime']=date("Y-m-d",time());
                    //更改抽奖项目状态
                    // $result=pdo_update('yzcj_sun_goods', $data, array('gid' =>$gid,'uniacid'=>$_W['uniacid']));
                    //获取参与了此次抽奖的用户
                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));

                    //组团开奖
                    if($res['state']==3){
                        shuffle($order);
                        //一二三等奖
                        if($res['one']==1){
                            $ZorderPro=[];
                            //指定人开奖
                            if($res['zuid']!=0){
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];

                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                shuffle($order);
                                $orderProYes=[];
                                $orderProNo=[];

                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$zcount){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }

                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                //未中奖
                                $data4['status']=4;
                                $data4['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                $orderProNo=[];

                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$count){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }

                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            

                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                foreach($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        // $data3['status']=2;
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $zcount=$res['count']-1;
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                }
                            }else{
                                //筛选
                                // $orderProYes=[];
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                }
                            }
                        }
                    }
                    //抽奖码开奖
                    else if($res['state']==4){
                        // p($order);
                        $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                        //打乱数组
                        shuffle($code);
                        if($res['one']==1){
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$count){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // p($orderProYes);
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['count']-1;
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                //筛选
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$res['count']){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                $orderProNo=$code;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {

                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }
                        
                    }
                    //普通开奖
                    else{
                        //打乱数组
                        shuffle($order);
                        if($res['one']==1){
                            $count=$res['onenum']-1+$res['twonum']+$res['threenum'];

                            $ZorderPro=[];
                            if($res['zuid']!=0){ 
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                //中奖处理
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$count);
                                $orderProNo=array_slice($ZorderPro,$count);
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                                
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];

                                //筛选
                                $orderProYes=array_slice($order,0,$count);
                                $orderProNo=array_slice($order,$count);
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }else{
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        if($res['cid']==2){
                                            $userid=$value['uid'];
                                            $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                            $nmoney=$umoney+$res['gname'];
                                            $data4['money']=$nmoney;
                                            $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                        }
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $zcount=$res['count']-1;
                                //筛选
                                shuffle($ZorderPro);
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }else{

                                //筛选
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }
                        
                    }

                    // if($res['zuid']!=0){
                    //     foreach ($order as $key => $value) {
                    //         if($value['uid']==$res['zuid']){
                    //             if($res['cid']==2){
                    //                 $userid=$value['uid'];
                    //                 $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //                 $nmoney=$umoney+$res['gname'];
                    //                 $data4['money']=$nmoney;
                    //                 $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //             }
                    //             $oid=$value['oid'];
                    //             $data2['status']=2;
                    //             $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //         }else{
                    //             array_push($ZorderPro,$value);
                    //         }
                    //     }
                    //     $zcount=$res['count']-1;
                    //     //筛选
                    //     //筛选
                    //     shuffle($ZorderPro);
                    //     $orderProYes=array_slice($ZorderPro,0,$zcount);
                    //     $orderProNo=array_slice($ZorderPro,$zcount);
                    //     //随机中奖
                    //     foreach ($orderProYes as $key => $value) {
                    //         if($res['cid']==2){
                    //             $userid=$value['uid'];
                    //             $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //             $nmoney=$umoney+$res['gname'];
                    //             $data4['money']=$nmoney;
                    //             $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //         }
                    //         $oid=$value['oid'];
                    //         $data2['status']=2;
                    //         $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    //     //未中奖
                    //     foreach ($orderProNo as $key => $value) {
                    //         $oid=$value['oid'];
                    //         $data3['status']=4;
                    //         $result1=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    // }else{
                    //     //筛选
                    //     shuffle($order);
                    //     $orderProYes=array_slice($order,0,$res['count']);
                    //     $orderProNo=array_slice($order,$res['count']);
                    //     //随机中奖
                    //     foreach ($orderProYes as $key => $value) {
                    //         if($res['cid']==2){
                    //             $userid=$value['uid'];
                    //             $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                    //             $nmoney=$umoney+$res['gname'];
                    //             $data4['money']=$nmoney;
                    //             $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                    //         }
                    //         $oid=$value['oid'];
                    //         $data2['status']=2;
                    //         $result2=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    //     //未中奖
                    //     foreach ($orderProNo as $key => $value) {
                    //         $oid=$value['oid'];
                    //         $data3['status']=4;
                    //         $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                    //     }
                    // }

                    $info = array(
                        'num' => 10002
                    );
                    echo json_encode($info);
                }else{
                    $info = array(
                        'num' => 10001,
                        'res' => $res,
                        'ad' => $ad1
                    );
                    // p($info);
                    echo json_encode($info);
                }
            }else{
                $info = array(
                        'num' => 10001,
                        'res' => $res,
                        'ad' => $ad1
                    );
                    // p($info);
                    echo json_encode($info);
            }
            
        }else{
            $info = array(
                'num' => 10002
                // 'res' => $res,
                // 'ad' => $ad1
            );
            echo json_encode($info);
        }
        
        
    }
    //抽奖人数
    public function doPageProNum(){
        global $_GPC, $_W;
        $gid=$_GPC['gid'];
        $uid=$_GPC['uid'];
        $res=[];
        $img=[];
        $img1=[];
        $pagesize = 100;
        // $pageindex = intval($_GPC['page'])*$pagesize;
        $pageindex = max(1, intval($_GPC['page']));
        //查询人数
        $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
        // array_push($img,$total);
        $res['total']=$total;
        $uidarr=pdo_fetchall("select uid from ".tablename('yzcj_sun_order')." where gid = '$gid' and uniacid=".$_W['uniacid']." limit ".($pageindex - 1) * $pagesize.",".$pagesize);
        // p("select uid from ".tablename('yzcj_sun_order')." where gid = "."'$gid' and uniacid=".$_W['uniacid']." limit ".($pageindex - 1) * $pagesize.",".$pagesize);
        
        // shuffle($uidarr);
        $user=pdo_fetchall("select uid from ".tablename('yzcj_sun_order')." where gid = '$gid' and uniacid=".$_W['uniacid']);
        foreach ($user as $key => $value) {
            if($value['uid']==$uid){
                $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id='$uid' and uniacid=".$_W['uniacid']);
                array_push($img1,$res1);
            }
        }
        foreach ($uidarr as $key => $value) {
            if($value['uid']!=$uid){
                // if(count($img)<6){
                    $id=$value['uid'];
                    $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id='$id' and uniacid=".$_W['uniacid']);
                    array_push($img,$res1);
                // }
            }
        }
        $res['img']=$img;
        $res['img1']=$img1;
        $res['count']=count($img)+count($img1);
        echo json_encode($res);
    }
    //图片上传
    public function doPageToupload(){
        global $_GPC,$_W;

         $uptypes=array(  
            'image/jpg',  
            'image/jpeg',  
            'image/png',  
            'image/pjpeg',  
            'image/gif',  
            'image/bmp',  
            'image/x-png'  
            );  
    $max_file_size=2000000;     //上传文件大小限制, 单位BYTE  
    $year=date("Y/m",time());
 //   $destination_folder="../attachment/zh_tcwq/".$_W['uniacid']."/".date(Y)."/".date(m)."/".date(d)."/"; //上传文件路径  
 $destination_folder="../attachment/"; //上传文件路径  
    $watermark=2;      //是否附加水印(1为加水印,其他为不加水印);  
    $watertype=1;      //水印类型(1为文字,2为图片)  
    $waterposition=1;     //水印位置(1为左下角,2为右下角,3为左上角,4为右上角,5为居中);  
    $waterstring="666666";  //水印字符串  
    // $waterimg="xplore.gif";    //水印图片  
    $imgpreview=1;      //是否生成预览图(1为生成,其他为不生成);  
    $imgpreviewsize=1/2;    //缩略图比例 
    // echo json_encode($_FILES);die;
    if (!is_uploaded_file($_FILES["file"]['tmp_name']))  
    //是否存在文件  
    {  
     echo "图片不存在!";  
     exit;  
   }
   $file = $_FILES["file"];
   if($max_file_size < $file["size"])
    //检查文件大小  
   {
    echo "文件太大!";
    exit;
  }
  if(!in_array($file["type"], $uptypes))  
    //检查文件类型
  {
    echo "文件类型不符!".$file["type"];
    exit;
  }
  if(!file_exists($destination_folder))
  {
    mkdir($destination_folder);
  }  
  $filename=$file["tmp_name"];  
  $image_size = getimagesize($filename);  
  $pinfo=pathinfo($file["name"]);  
  $ftype=$pinfo['extension'];  
  $destination = $destination_folder.str_shuffle(time().rand(111111,999999)).".".$ftype;  
  if (file_exists($destination) && $overwrite != true)  
  {  
    echo "同名文件已经存在了";  
    exit;  
  }  
  if(!move_uploaded_file ($filename, $destination))  
  {  
    echo "移动文件出错";  
    exit;
  }
  $pinfo=pathinfo($destination);  
  $fname=$pinfo['basename'];  
    // echo " <font color=red>已经成功上传</font><br>文件名:  <font color=blue>".$destination_folder.$fname."</font><br>";  
    // echo " 宽度:".$image_size[0];  
    // echo " 长度:".$image_size[1];  
    // echo "<br> 大小:".$file["size"]." bytes";  
  if($watermark==1)  
  {  
    $iinfo=getimagesize($destination,$iinfo);  
    $nimage=imagecreatetruecolor($image_size[0],$image_size[1]);
    $white=imagecolorallocate($nimage,255,255,255);
    $black=imagecolorallocate($nimage,0,0,0);
    $red=imagecolorallocate($nimage,255,0,0);
    imagefill($nimage,0,0,$white);
    switch ($iinfo[2])
    {  
      case 1:
      $simage =imagecreatefromgif($destination);
      break;
      case 2:
      $simage =imagecreatefromjpeg($destination);
      break;
      case 3:
      $simage =imagecreatefrompng($destination);
      break;
      case 6:
      $simage =imagecreatefromwbmp($destination);
      break;
      default:
      die("不支持的文件类型");
      exit;
    }
    imagecopy($nimage,$simage,0,0,0,0,$image_size[0],$image_size[1]);
    imagefilledrectangle($nimage,1,$image_size[1]-15,80,$image_size[1],$white);
    switch($watertype)  
    {
            case 1:   //加水印字符串
            imagestring($nimage,2,3,$image_size[1]-15,$waterstring,$black);
            break;
            case 2:   //加水印图片
            $simage1 =imagecreatefromgif("xplore.gif");
            imagecopy($nimage,$simage1,0,0,0,0,85,15);
            imagedestroy($simage1);
            break;
          }
          switch ($iinfo[2])
          {
            case 1:
            //imagegif($nimage, $destination);
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
            //imagejpeg($nimage, $destination);
            break;
          }
        //覆盖原上传文件
          imagedestroy($nimage);
          imagedestroy($simage);
        }
    // if($imgpreview==1)  
    // {  
    // echo "<br>图片预览:<br>";  
    // echo "<img src=\"".$destination."\" width=".($image_size[0]*$imgpreviewsize)." height=".($image_size[1]*$imgpreviewsize);  
    // echo " alt=\"图片预览:\r文件名:".$destination."\r上传时间:\">";  
    // } 
        echo $fname;
        @require_once (IA_ROOT . '/framework/function/file.func.php');
        @$filename=$fname;
        @file_remote_upload($filename); 
    }
    //获取抽奖类型和红包费率
    public function doPageGetRed(){
        global $_GPC,$_W;
        $red=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        echo json_encode($red);
    }
    //高级抽奖选项
    public function doPageGetsenior(){
        global $_GPC,$_W;
        $red=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),array('paidprice','passwordprice','growpprice','codeprice','oneprice'));
        echo json_encode($red);
    }
    //高级抽奖发起页面
    public function doPageGetseniorpage(){
        global $_GPC,$_W;
        $red=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        echo json_encode($red);
    }
    //发起高级抽奖
    public function doPageaddSeniorPro(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        //先判断登录用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>2),'sid')['sid'];

        $data['cid']=$_GPC['awardtype'];
        $data['gName']=$_GPC['gName'];
        $data['count']=$_GPC['count'];
        $data['condition']=$_GPC['index'];
        $data['accurate']=$_GPC['accurate'];
        $data['selftime']=date('Y-m-d H:i:s',time());
        $data['uniacid']=$_W['uniacid'];
        $data['status']=$_GPC['status'];
        $data['zuid']=0;
        if($_GPC['imgSrc']==''){
            $data['pic']='';
        }else{
            $data['pic']=$_GPC['imgSrc'];
        }
        if($_GPC['paidprice']){
            $data['state']=1;
            $data['paidprice']=$_GPC['paidprice'];
        }
        else if($_GPC['password']){
            $data['state']=2;
            $data['password']=$_GPC['password'];
        }
        else if($_GPC['group']){
            $data['state']=3;
            $data['group']=$_GPC['group'];
        }
        else if($_GPC['codenum']){
            $data['state']=4;
            $data['codenum']=$_GPC['codenum'];
            $data['codemost']=$_GPC['codemost'];
            // $data['codecount']=$_GPC['codecount'];
            $data['codeway']=$_GPC['codeway'];
        }else{
            $data['state']=5;
        }
        if($_GPC['onename']){
            $data['one']=1;
            $data['onename']=$_GPC['onename'];
            $data['onenum']=$_GPC['onenum'];
            $data['twoname']=$_GPC['twoname'];
            $data['twonum']=$_GPC['twonum'];
            $data['threename']=$_GPC['threename'];
            $data['threenum']=$_GPC['threenum'];
        }else{
            $data['one']=2;
        }
  
        
        if(!empty($sid)){//赞助商
            $data['sid']=$sid;
            $res=pdo_insert('yzcj_sun_goods',$data);
        }else{//用户
            $data['uid']=$uid;
            $res=pdo_insert('yzcj_sun_goods',$data);
        };
        $gid = pdo_insertid();
        echo json_encode($gid);
    }
    //高级抽奖图片上传
    public function doPageToupload2(){
        global $_GPC,$_W;
        $id = $_GPC["id"];
        // p($id);
        // die;
        $uptypes=array(  
            'image/jpg',  
            'image/jpeg',  
            'image/png',  
            'image/pjpeg',  
            'image/gif',  
            'image/bmp',  
            'image/x-png'  
        );  
        $max_file_size=2000000;     //上传文件大小限制, 单位BYTE  
     
        $destination_folder="../attachment/"; //上传文件路径  
        $imgpreview=1;      //是否生成预览图(1为生成,其他为不生成);  
        $imgpreviewsize=1/2;    //缩略图比例 
        if (!is_uploaded_file($_FILES["file"]['tmp_name']))  
        //是否存在文件  
        {  
         echo "图片不存在!";  
         exit;  
        }
       $file = $_FILES["file"];
       if($max_file_size < $file["size"])
        //检查文件大小  
       {
        echo "文件太大!";
        exit;
       }
      if(!in_array($file["type"], $uptypes))  
        //检查文件类型
      {
        echo "文件类型不符!".$file["type"];
        exit;
      }
      if(!file_exists($destination_folder))
      {
        mkdir($destination_folder);
      }  
      $filename=$file["tmp_name"];  
      $image_size = getimagesize($filename);  
      $pinfo=pathinfo($file["name"]);  
      $ftype=$pinfo['extension'];  
      $destination = $destination_folder.str_shuffle(time().rand(111111,999999)).".".$ftype;  
      if (file_exists($destination) && $overwrite != true)  
      {  
        echo "同名文件已经存在了";  
        exit;  
      }  
      if(!move_uploaded_file ($filename, $destination))  
      {  
        echo "移动文件出错";  
        exit;
      }
      $pinfo=pathinfo($destination);  
      $fname=$pinfo['basename'];  

        $newimg = $fname;
        //获取数据库图片
        $img = pdo_getcolumn("yzcj_sun_goods", array('gid' => $id,'uniacid'=>$_W['uniacid']), 'img');
        //$img = pdo_getcolumn($tablearr[$types], array('id' => $tcid), 'img');
        if($img){
            $data["img"] = $img.",".$newimg;
        }else{
            $data["img"] = $newimg;
        }
        $res=pdo_update("yzcj_sun_goods",$data,array('gid'=>$id,'uniacid'=>$_W['uniacid']));
        echo json_encode($res);
        echo $fname;
        @require_once (IA_ROOT . '/framework/function/file.func.php');
        @$filename=$fname;
        @file_remote_upload($filename); 
    }


    //发起抽奖
    public function doPageAddPro(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        //先判断登录用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>2),'sid')['sid'];

        $data['cid']=$_GPC['awardtype'];
        $data['gName']=$_GPC['gName'];
        $data['count']=$_GPC['count'];
        $data['condition']=$_GPC['index'];
        $data['accurate']=$_GPC['accurate'];
        $data['selftime']=date('Y-m-d H:i:s',time());
        $data['uniacid']=$_W['uniacid'];
        $data['status']=$_GPC['status'];
        $data['zuid']=0;
        $data['state']=5;
        $data['one']=2;
        if($_GPC['imgSrc']==''){
            $data['pic']='';
        }else{
            $data['pic']=$_GPC['imgSrc'];
        }
        if(!empty($sid)){//赞助商
            $data['sid']=$sid;
            $res=pdo_insert('yzcj_sun_goods',$data);
        }else{//用户
            $data['uid']=$uid;
            $res=pdo_insert('yzcj_sun_goods',$data);
        };
        $gid = pdo_insertid();
        echo json_encode($gid);
        
    }
    //获取皮一下抽奖
    public function doPageGetPi(){
        global $_GPC,$_W;
        $res=pdo_getall('yzcj_sun_goodspi',array('uniacid'=>$_W['uniacid']));
        echo json_encode($res);
    }
    //发起皮一下抽奖
    public function doPageAddPI(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        // $current=$_GPC['current']+1;
        $data['gname']=$_GPC['gname'];
        $data['count']=$_GPC['count'];
        $data['cid']=4;
        $data['condition']=$_GPC['index'];
        $data['accurate']=$_GPC['accurate'];
        $data['selftime']=date('Y-m-d H:i:s',time());
        $data['uniacid']=$_W['uniacid'];
        $data['status']=$_GPC['status'];
        $data['zuid']=0;
        if($_GPC['imgSrc']==''){
            $data['pic']='';
        }else{
            $data['pic']=$_GPC['imgSrc'];
        }

        $data['uid']=$uid;
        $res=pdo_insert('yzcj_sun_goods',$data);

        $gid = pdo_insertid();
        echo json_encode($gid);
    }
    //获取礼物信息
    public function doPagegetGift(){
        global $_GPC,$_W;
        $giftId=$_GPC['giftId'];
        $count=$_GPC['count'];
        $gift=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$giftId));
        $gift['imgSrc']= explode(',',$gift['pic']);
        $gift['imgSrc']=$gift['imgSrc']['0'];
        if($gift['count']<$count){
            $info=array(
                'num'=>'1',
                'gift'=>$gift,
            );
            echo json_encode($info);
        }else{
            echo 2;
        }
    }
    //发起送礼
    public function doPageAddGift(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        //先判断登录用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>2),'sid')['sid'];

        $data['cid']=$_GPC['awardtype'];
        $data['gName']=$_GPC['gName'];
        $data['count']=$_GPC['count'];
        $data['condition']=$_GPC['index'];
        $data['accurate']=$_GPC['accurate'];
        if($_GPC['lottery']==undefined){
            $data['lottery']='大吉大利，送你好礼！';
        }else{
            $data['lottery']=$_GPC['lottery'];
        }
        
        $data['selftime']=date('Y-m-d H:i:s',time());
        $data['uniacid']=$_W['uniacid'];
        $data['status']=$_GPC['status'];
        $data['zuid']=0;
        
        if($_GPC['imgSrc']==''){
            $data['pic']='';
        }else{
            $data['pic']=$_GPC['imgSrc'];
        }

        $giftid=$_GPC['giftId'];
        $data['giftId']=$giftid;
        $gifts=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$giftid));
        $data1['count']=$gifts['count']-$_GPC['count'];
        $res=pdo_update('yzcj_sun_gifts',$data1,array('id'=>$giftid,'uniacid'=>$_W['uniacid']));
        //获取礼物商家的用户ID
        // $usid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$gifts['sid']),'uid')['uid'];
        // //获取余额
        // $money=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$usid),'money')['money'];
        // $data2['money']=$money+$_GPC['price'];
        // $result=pdo_update('yzcj_sun_user',$data2,array('id'=>$usid,'uniacid'=>$_W['uniacid']));

        
            if(!empty($sid)){//赞助商
                $data['sid']=$sid;
                $res=pdo_insert('yzcj_sun_goods',$data);
            }else{//用户
                $data['uid']=$uid;
                $res=pdo_insert('yzcj_sun_goods',$data);
            };
            $gid = pdo_insertid();
            echo json_encode($gid);

    }
    //删除礼物商品
    public function doPagedelGift(){
        global $_GPC,$_W;
        $gid=$_GPC['gid'];
        $id=$_GPC['giftId'];
        $res=pdo_delete('yzcj_sun_goods',array('gid'=>$_GPC['gid'],'uniacid'=>$_W['uniacid']));
        $count=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$id),'count')['count'];
        $data['count']=$count+1;
        $res=pdo_update('yzcj_sun_gifts',$data,array('id'=>$id,'uniacid'=>$_W['uniacid']));

    }
    //抽奖编辑
    public function doPageEditor(){
        global $_GPC,$_W;
        $gid=$_GPC['gid'];
        $goods=pdo_get('yzcj_sun_goods',array('gid'=>$gid,'uniacid'=>$_W['uniacid']));
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        // $goods=$this->sliceArr($goods);
        $goods['code_img']='';
        $goods['time']=strtotime($goods['accurate']);
        $info=array(
            'goods'=>$goods,
            'cjzt'=>$cjzt,
        );
        echo json_encode($info);
    }
    //编辑修改
    // public function doPageGetEditor(){
    //     global $_GPC,$_W;
    //     $gid=$_GPC['gid'];
    //     $goods=pdo_getall('yzcj_sun_goods',array('gid'=>$gid,'uniacid'=>$_W['uniacid']));
    //     $goods=$this->sliceArr($goods);

    // }
    //参与抽奖
    public function doPageTakePro(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $gid=$_GPC['gid'];
        $state=$_GPC['state'];
        // $state=4;
        // $gid=157;
        // $openid='ojKX54l5kEF9lpVz_bfG18fJyBQE';

        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
        // echo $uid;
        // $uid=5;
        //判断是否参与过
        $order=pdo_get('yzcj_sun_order',array('gid'=>$gid,'uid'=>$uid['id'],'uniacid'=>$_W['uniacid']));
        // p($order);die;
        if(empty($order)){
            //组团抽奖
            if($state==3){
                if($_GPC['invuid']!=undefined&&$_GPC['invuid']){
                    $invuid=$_GPC['invuid'];//邀请人的id
                    $goods=pdo_get("yzcj_sun_goods",array('uniacid'=>$_W['uniacid'],'gid'=>$gid),array('group'));
                    $group=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid),array('count(id) as count'));
                    // p($goods);
                    // p($group);
                    if($goods['group']<=$group['count']){
                        $info=array(
                            'num'=>10001,
                            'msg'=>'当前队伍已满！请自行参与抽奖！'
                        );
                        echo json_encode($info);
                    }else{
                        //生成订单号
                        $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                        //查询所有订单号，一旦重复，就重新生成
                        $allNum=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid']),'orderNum');
                        foreach ($allNum as $key => $value) {
                            if($value['orderNum']==$data['orderNum']){
                                $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                            }
                        }
                        $data['time']=date('Y-m-d H:i:s',time());
                        $data['uid']=$uid['id'];
                        $data['gid']=$gid;
                        $data['uniacid']=$_W['uniacid'];
                        $data['type']=$state;
                        $res=pdo_insert('yzcj_sun_order',$data);
                        $oid=pdo_insertid();
                        //组团表
                        $data1['oid']=$oid;
                        $data1['uid']=$uid['id'];
                        $data1['invuid']=$invuid;
                        $data1['gid']=$gid;
                        $data1['uniacid']=$_W['uniacid'];
                        $res1=pdo_insert('yzcj_sun_group',$data1);
                        // echo json_encode($oid);
                        $info=array(
                            'num'=>10002,
                            'oid'=>$oid,
                            // 'msg'=>'当前队伍已满！请自行参与抽奖！'
                        );
                        echo json_encode($info);
                    }
                }else{
                    //生成订单号
                    $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                    //查询所有订单号，一旦重复，就重新生成
                    $allNum=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid']),'orderNum');
                    foreach ($allNum as $key => $value) {
                        if($value['orderNum']==$data['orderNum']){
                            $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                        }
                    }
                    $data['time']=date('Y-m-d H:i:s',time());
                    $data['uid']=$uid['id'];
                    $data['gid']=$gid;
                    $data['uniacid']=$_W['uniacid'];
                    $data['type']=$state;
                    $res=pdo_insert('yzcj_sun_order',$data);
                    $oid=pdo_insertid();
                    $info=array(
                        'num'=>10002,
                        'oid'=>$oid,
                    );
                    echo json_encode($info);

                }
            }
            //抽奖码抽奖
            else if($state==4){

                $goods=pdo_get("yzcj_sun_goods",array('uniacid'=>$_W['uniacid'],'gid'=>$gid),array('codenum','codemost','codeway'));
                $code=pdo_get("yzcj_sun_code",array('uniacid'=>$_W['uniacid'],'gid'=>$gid),array('count(id) as count'));
                // p($goods);
                // p($code);die;
                if($code['count']>=$goods['codenum']){
                    $info=array(
                        'num'=>10003,
                        'msg'=>'无法再获取抽奖码！'
                    );
                    echo json_encode($info);
                }else{
                    //生成订单号
                    $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                    //查询所有订单号，一旦重复，就重新生成
                    $allNum=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid']),'orderNum');
                    foreach ($allNum as $key => $value) {
                        if($value['orderNum']==$data['orderNum']){
                            $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                        }
                    }
                    $data['time']=date('Y-m-d H:i:s',time());
                    $data['uid']=$uid['id'];
                    $data['gid']=$gid;
                    $data['uniacid']=$_W['uniacid'];
                    $data['type']=$state;
                    $res=pdo_insert('yzcj_sun_order',$data);
                    $oid=pdo_insertid();

                    //抽奖码表
                    $data1['oid']=$oid;
                    $data1['uid']=$uid['id'];
                    $data1['invuid']=$uid['id'];
                    $data1['gid']=$gid;
                    $data1['uniacid']=$_W['uniacid'];
                    $res1=pdo_insert('yzcj_sun_code',$data1);

                    // $_GPC['invuid']=6;
                    if($_GPC['invuid']!=undefined&&$_GPC['invuid']){
                        
                        if($goods['codeway']==2){
                            $invuid=$_GPC['invuid'];//邀请人的id
                            $usercode=pdo_get("yzcj_sun_code",array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$invuid),array('count(id) as count'));
                            
                            if($code['count']<$goods['codenum']){
                                if($usercode['count']<$goods['codemost']){
                                    //抽奖码表
                                    $data2['oid']=$oid;
                                    $data2['uid']=$uid['id'];
                                    $data2['invuid']=$invuid;
                                    $data2['gid']=$gid;
                                    $data2['uniacid']=$_W['uniacid'];
                                    $res2=pdo_insert('yzcj_sun_code',$data2);
                                }
                            }


                        }

                        
                    }

                }
                // echo json_encode($oid);
                $info=array(
                    'num'=>10002,
                    'oid'=>$oid
                );
                echo json_encode($info);
                

                
            }else{
                //生成订单号
                $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                //查询所有订单号，一旦重复，就重新生成
                $allNum=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid']),'orderNum');
                foreach ($allNum as $key => $value) {
                    if($value['orderNum']==$data['orderNum']){
                        $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                    }
                }
                $data['time']=date('Y-m-d H:i:s',time());
                $data['uid']=$uid['id'];
                $data['gid']=$gid;
                $data['uniacid']=$_W['uniacid'];
                $data['type']=$state;
                $res=pdo_insert('yzcj_sun_order',$data);
                $oid=pdo_insertid();
                
                $info=array(
                    'num'=>10002,
                    'oid'=>$oid
                );
                echo json_encode($info);
            }
        }
    }
    //发起组队
    public function doPageGoGroup(){
        global $_W,$_GPC;

        $res=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$_GPC['gid'],'uid'=>$_GPC['uid']));
        if(empty($res)){
            $data['oid']=$_GPC['oid'];
            $data['uid']=$_GPC['uid'];
            $data['invuid']=$_GPC['uid'];
            $data['gid']=$_GPC['gid'];
            $data['uniacid']=$_W['uniacid'];
            $res=pdo_insert('yzcj_sun_group',$data);
        }

        
    }

    //个人中心
    public function doPageMy(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];

        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        //余额
        $money=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'money')['money'];
        //全部抽奖
        $allnum=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join".tablename('yzcj_sun_goods')."b on b.gid=a.gid where a.uid="."'$uid' and a.status!=2 and a.status!=5 and a.status!=6 and b.cid !=3 and a.uniacid=".$_W['uniacid']);

        //发起抽奖
        //先判断登录用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid']),'sid')['sid'];
        // if(!empty($sid)){
            //赞助商
            $launchnum1=pdo_fetchcolumn("SELECT count(gid) FROM ".tablename('yzcj_sun_goods')." where sid="."'$sid' and status!=1 and status!=3 and cid!=3 and uniacid=".$_W['uniacid']);
        // }else{
            //用户
            $launchnum2=pdo_fetchcolumn("SELECT count(gid) FROM ".tablename('yzcj_sun_goods')." where uid="."'$uid' and status!=1 and status!=3 and cid!=3 and uniacid=".$_W['uniacid']);
        // };
        $launchnum=$launchnum1+$launchnum2;

        //中奖记录
        $luckynum=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join".tablename('yzcj_sun_goods')."b on b.gid=a.gid where a.uid='$uid' and a.status=2 and b.cid !=3 and a.uniacid=".$_W['uniacid']."||a.uid='$uid' and a.status=5 and b.cid !=3 and a.uniacid=".$_W['uniacid']."|| a.uid='$uid' and a.status=6 and b.cid !=3 and a.uniacid=".$_W['uniacid']);

        $res=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $info = array(
            'money' => $money,
            'allnum' => $allnum,
            'launchnum' => $launchnum,
            'luckynum' => $luckynum,
            'res' => $res
        );
        echo json_encode($info);
    }
    

    //全部抽奖和中奖记录
    public function doPageAllPro(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $status=$_GPC['status'];
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        if($status=='1'){
            //待开奖
            $where="where a.uid="."'$uid' and a.status='1' and b.cid!=3  and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,b.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid ".$where." ORDER BY a.time desc";
            $WaitPro = pdo_fetchall($sql);
            $WaitPro=$this->sliceArr($WaitPro);
            //未中奖
            $where1="where a.uid="."'$uid' and a.status='4' and b.cid!=3 and a.uniacid=".$_W['uniacid'];
            $sql1 = "select a.*,b.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid ".$where1." ORDER BY a.time desc";
            $OverPro = pdo_fetchall($sql1);
            $OverPro=$this->sliceArr($OverPro);
            //已失效
            $where2="where a.uid="."'$uid' and a.status='3' and b.cid!=3 and a.uniacid=".$_W['uniacid'];
            $sql2 = "select a.*,b.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid ".$where2." ORDER BY a.time desc";
            $FailPro = pdo_fetchall($sql2);
            $FailPro=$this->sliceArr($FailPro); 
        }else{
            //中奖
            $where="where a.uid='$uid' and a.status='2' and b.cid!=3 and a.uniacid=".$_W['uniacid']."|| a.uid='$uid' and a.status='5' and b.cid!=3 and a.uniacid=".$_W['uniacid']."|| a.uid='$uid' and a.status='6' and b.cid!=3 and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,b.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid ".$where." ORDER BY a.time desc";
            $WaitPro = pdo_fetchall($sql);
            $WaitPro=$this->sliceArr($WaitPro);
        }
        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        $info = array(
            'WaitPro' => $WaitPro,
            'OverPro' => $OverPro,
            'FailPro' => $FailPro,
            'cjzt' => $cjzt,
        );
        echo json_encode($info);
    }
    public function sliceArr($array){
        foreach($array as $k=>$v){
            $array[$k]["code_img"] = '';
        }
        return $array;
    }
    //发起抽奖
    public function doPageIniPro(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];

        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        //先判断登录用户是不是赞助商用户
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid']),'sid')['sid'];

        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        // p($sid);
        if(!empty($sid)){
            //赞助商
            //待开奖
            $WaitPro=pdo_getall('yzcj_sun_goods',array('sid'=>$sid,'uniacid'=>$_W['uniacid'],'status'=>'2','cid !='=>'3'),array() , '' , 'selftime DESC');
            
            $WaitPro=$this->sliceArr($WaitPro);

            //已结束
            $OverPro=pdo_getall('yzcj_sun_goods',array('sid'=>$sid,'uniacid'=>$_W['uniacid'],'status'=>'4','cid !='=>'3'),array() , '' , 'endtime DESC');

            $OverPro=$this->sliceArr($OverPro);
            // p($OverPro2);
            //已失效
            $FailPro=pdo_getall('yzcj_sun_goods',array('sid'=>$sid,'uniacid'=>$_W['uniacid'],'status'=>'5','cid !='=>'3'),array() , '' , 'selftime DESC');
            $FailPro=$this->sliceArr($FailPro);
        }
        // else{
            //用户
            // //待开奖
            $WaitPro1=pdo_getall('yzcj_sun_goods',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>'2','cid !='=>'3'),array() , '' , 'selftime DESC');
            $WaitPro1=$this->sliceArr($WaitPro1);
            //已结束
            $OverPro1=pdo_getall('yzcj_sun_goods',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>'4','cid !='=>'3'),array() , '' , 'endtime DESC');
            $OverPro1=$this->sliceArr($OverPro1);
            //已失效
            $FailPro1=pdo_getall('yzcj_sun_goods',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'status'=>'5','cid !='=>'3'),array() , '' , 'selftime DESC');
            $FailPro1=$this->sliceArr($FailPro1);
        // };
        $info = array(
            'WaitPro' => $WaitPro,
            'OverPro' => $OverPro,
            'FailPro' => $FailPro,
            'WaitPro1' => $WaitPro1,
            'OverPro1' => $OverPro1,
            'FailPro1' => $FailPro1,
            'cjzt'=>$cjzt
        );
        echo json_encode($info);
    }


    
    //礼物记录
    public function doPageMyGift(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
          //  var_dump($openid);

        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
     // var_dump($uid);
        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid']),'sid')['sid'];
      //var_dump($sid);
        $goods=pdo_getall('yzcj_sun_goods',array('uniacid'=>$_W['uniacid']));
              $goods=$this->sliceArr($goods);

        // 判断是否是礼物
        foreach ($goods as $key => $value) {

            if($value['cid']==3){
                //判断是赞助商还是用户发起的抽奖
                //我发起的
                if(empty($sid)){
                    //打印
                    $where="where a.uid="."'$uid' and a.cid =3 and a.uniacid=".$_W['uniacid'];
                    $sql = "select a.*,a.status as statuss,a.count as count1,a.pic as img,b.* from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_gifts')."b on b.id=a.giftId ".$where." ORDER BY a.status asc";
                    $res = pdo_fetchall($sql);
                    $res=$this->sliceArr($res);

                }else{
                    //打印
                    $where="where a.sid="."'$sid' and a.cid =3  and a.uniacid=".$_W['uniacid']."|| a.uid='$uid'  and a.cid =3 and a.uniacid=".$_W['uniacid'];
                    $sql = "select a.*,a.status as statuss,a.count as count1,a.pic as img,b.* from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_gifts')."b on b.id=a.giftId ".$where." ORDER BY a.status asc";
                    $res = pdo_fetchall($sql);
                    $res=$this->sliceArr($res);

                    // p($res);
                }
                //我参与的
                $where1="where a.uid="."'$uid' and a.status!=2 and a.status!=5 and b.cid =3 and a.status!=6 and a.uniacid=".$_W['uniacid'];
                $sql1 = "select a.*,a.status as statuss,b.*,b.count as count1,b.pic as img,c.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join ".tablename('yzcj_sun_gifts')."c on c.id = b.giftId ".$where1." ORDER BY a.status asc";
                $WaitPro = pdo_fetchall($sql1);
                    $WaitPro=$this->sliceArr($WaitPro);

                //我收到的
                $where2="where a.uid="."'$uid' and a.status='2' and b.cid =3 and a.uniacid=".$_W['uniacid']." || a.uid="."'$uid' and a.status='5' and b.cid =3 and a.uniacid=".$_W['uniacid']." || a.uid="."'$uid' and a.status='6' and b.cid =3 and a.uniacid=".$_W['uniacid'];
                $sql2 = "select a.*,a.status as statuss,b.*,b.count as count1,b.pic as img,c.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join ".tablename('yzcj_sun_gifts')."c on c.id = b.giftId ".$where2." ORDER BY a.adid asc , a.status asc";
                $LuckyPro = pdo_fetchall($sql2);
                    $LuckyPro=$this->sliceArr($LuckyPro);
                
            }
        }
        

        $info=array(
            'res'=>$res,
            'WaitPro'=>$WaitPro,
            'LuckyPro'=>$LuckyPro,
        );
        echo json_encode($info);
    }
    //确认收货
    public function doPageConfirm(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];
        //通过订单ID获取礼物价钱，并充值进礼物主人帐号
        $where="where a.oid='$oid' and a.uniacid=".$_W['uniacid'];
        $sql = "select c.price,c.sid from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join ".tablename('yzcj_sun_gifts')."c on c.id = b.giftId ".$where;
        $result = pdo_fetch($sql);
        $sid=$result['sid'];
        $uid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$sid),'uid')['uid'];
        $money=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$uid),'money')['money'];
        $data1['money']=$money+$result['price'];
        $res1=pdo_update('yzcj_sun_user',$data1,array('uniacid'=>$_W['uniacid'],'id'=>$uid));
        //更改订单状态为已收货
        $data['status']=5;
        $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));

    }
    //确认收货
    public function doPagesure(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];

        //更改订单状态为已收货
        $data['status']=5;
        $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));

    }
    //变更转赠状态
    public function doPageExamples(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];
        $data['state']=2;
        $order=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));
    }
    //添加订单
    public function doPageAddOrder(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $gid=$_GPC['gid'];
        $oid=$_GPC['oid'];
        // p($oid);
        // p($gid);
        
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        // p($uid);
        if($oid==undefined||$oid==''){
            
            $count=pdo_get('yzcj_sun_goods',array('uniacid'=>$_W['uniacid'],'gid'=>$gid),'count')['count'];
            $orderCount=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
            $order=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'uid'=>$uid));
            // p($order);
            // p($count);
            // p($orderCount);
            if(empty($order)){
                if($count>$orderCount){
                    //生成订单号
                    $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                    //查询所有订单号，一旦重复，就重新生成
                    $allNum=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid']),'orderNum');
                    foreach ($allNum as $key => $value) {
                        if($value['orderNum']==$orderNum){
                            $data['orderNum']=date('Ymdhi',time()).rand(10000,99999);
                        }
                    }
                    $data['time']=date('Y-m-d H:i:s',time());
                    $data['uid']=$uid;
                    $data['gid']=$gid;
                    $data['status']=2;
                    $data['uniacid']=$_W['uniacid'];
                    $res=pdo_insert('yzcj_sun_order',$data);
                    echo 2;
                }else{
                    echo 1;
                }
            }else{
                echo 1;
            }
        }else{
            $order=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$oid));
            if($order['state']==2){
                echo 1;
            }else{
                $data['state']=2;
                $data['uid']=$uid;
                $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));
                echo 2;
            }
        }
    }
    //发起抽奖详情
    public function doPageIniProDetail(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];

        $gid=$_GPC['gid'];
        $userid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $sid=pdo_get('yzcj_sun_goods',array('gid'=>$gid,'uniacid'=>$_W['uniacid']),'sid')['sid'];
        //判断是赞助商还是用户发起的抽奖
        if(empty($sid)){
            //打印
            $where="where a.gid='$gid' and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,a.status as astatus,b.* from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid ".$where;
            $res = pdo_fetch($sql);
            $res['code_img']='';

            // $res=$this->sliceArr($res);

            $oid=pdo_get('yzcj_sun_order',array('uid'=>$userid,'gid'=>$gid,'uniacid'=>$_W['uniacid']),'oid')['oid'];
        }else{
            //打印
            $where="where a.gid='$gid' and a.uniacid=".$_W['uniacid'];
            $sql = "select a.*,a.status as astatus,b.* from ".tablename('yzcj_sun_goods')."a left join ".tablename('yzcj_sun_sponsorship')."b on b.sid=a.sid ".$where;
            $res = pdo_fetch($sql);
            $res['code_img']='';

            // $res=$this->sliceArr($res);
            $usid=pdo_get('yzcj_sun_sponsorship',array('sid'=>$sid,'uniacid'=>$_W['uniacid']),'uid')['uid'];
            $oid=pdo_get('yzcj_sun_order',array('uid'=>$usid,'gid'=>$gid,'uniacid'=>$_W['uniacid']),'oid')['oid'];
        }
        $res['allimg']=explode(',',$res['img']);
        //查询人数
        $total=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid='$gid' and uniacid=".$_W['uniacid']);
        //判断登录用户是否参与抽奖过
        if(!empty($oid)){
            $res['oid']=$oid;
            if($res['state']==3){
                $isgroup=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'gid'=>$gid));
                if($isgroup){
                    // $group=pdo_getall('yzcj_sun_group',arary("uniacid"=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']));
                    $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']));
                    $count=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'invuid'=>$isgroup['invuid']),array('count(id) as count'));
                    $res['grouparr']=$group;
                    $res['groupcount']=$count['count'];
                }
            }
        }else{
            $res['oid']=0;
        }
        $res['total']=$total;

        //查询用户头像
        $uidarr=pdo_fetchall("select uid from ".tablename('yzcj_sun_order')." where gid = "."'$gid' and uniacid=".$_W['uniacid']);
        $img=[];
        $img1=[];

        shuffle($uidarr);
        foreach ($uidarr as $key => $value) {
            if($value['uid']==$userid){
                $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id='$userid' and uniacid=".$_W['uniacid']);
                array_push($img1,$res1);
            }
        }
        foreach ($uidarr as $key => $value) {
            if($value['uid']!=$userid){
                if(count($img)<6){
                    $id=$value['uid'];
                    $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id="."'$id' and uniacid=".$_W['uniacid']);
                    array_push($img,$res1);
                }
            }
        }
        $res['img']=$img;
        $res['img1']=$img1;
        //填写了地址的人数
        $total1=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')." where gid='$gid' and adid!='' and uniacid=".$_W['uniacid']);
        $res['total1']=$total1;

        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        $res['cjzt']=$cjzt;

        //广告
        $ad=pdo_getall('yzcj_sun_ad',array('uniacid'=>$_W['uniacid'],'status'=>1,'type'=>1));
        //打乱数组
        shuffle($ad);
        $ad1=[];
        array_push($ad1,array_slice($ad,0,1));
        // p($res);die;
        //空数组
        $ZorderPro=[];
        if($res['astatus']==2){
            if($res['condition']==1){
                if($res['accurate']<=$total){
                    $data['status']=4;
                    $data['endtime']=date("Y-m-d",time());
                    //更改抽奖项目状态
                    $result=pdo_update('yzcj_sun_goods', $data, array('gid' =>$gid,'uniacid'=>$_W['uniacid']));
                    //获取参与了此次抽奖的用户
                    $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                    //组团开奖
                    if($res['state']==3){
                        shuffle($order);
                        //一二三等奖
                        if($res['one']==1){
                            $ZorderPro=[];
                            //指定人开奖
                            if($res['zuid']!=0){
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];

                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2,'one'=>1),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid,'status'=>1));

                                shuffle($order);
                                $orderProYes=[];
                                $orderProNo=[];
                                
                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$zcount){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }
                                // p($orderProYes);
                                // p($res);
                                // p($res['twonum']);
                                // p($res['threenum']);
                                // die;
                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    // p($res['onenum']-1);
                                    // p($res['twonum']);
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }
                                // p($one);
                                // p($two);
                                // p($three);
                                // die;
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }
                                }
                                //未中奖
                                $data4['status']=4;
                                $data4['one']=0;
                                $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                $orderProNo=[];

                                foreach ($order as $key => $value) {

                                    if(count($orderProYes)<$count){
                                    
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid'],'uid'=>$invuid['invuid']));
                                                $invorder=pdo_get('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'oid'=>$group['oid']));
                                                if(!empty($orderProYes)){
                                                    foreach ($orderProYes as $k => $v) {
                                                        if($v['oid']!=$invorder['oid']){
                                                            array_push($orderProYes,$invorder);
                                                        }
                                                    }
                                                }else{
                                                    array_push($orderProYes,$invorder);
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }else{
                                            if(!empty($orderProYes)){
                                                foreach ($orderProYes as $k => $v) {
                                                    if($v['oid']!=$order[$key]['oid']){
                                                        array_push($orderProYes,$order[$key]);
                                                    }
                                                }
                                            }else{
                                                array_push($orderProYes,$order[$key]);
                                            }
                                        }
                                    }

                                    $orderProYes=$this->array_unique_fb($orderProYes);
                                }

                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        // p($value);
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            

                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        //是否组团
                                        if($invuid){  
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            //判断是否组团成功
                                            if($isgroup['count']>=$res['group']){
                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                foreach ($group as $k => $v) {
                                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$v['oid'],'uniacid'=>$_W['uniacid']));
                                                }
                                            }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));

                                            }
                                        }else{
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            
                                        }
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                    $data4['status']=4;
                                    $data4['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data4, array('gid' =>$gid,'uniacid'=>$_W['uniacid'],'status'=>1));

                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                // p($order); 
                                foreach($order as $key => $value) {

                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        // $data3['status']=2;
                                        // $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                        $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                        // p($invuid);
                                        //是否组团
                                        if($invuid){ 
                                            $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                            // p($res['group']);
                                            //判断是否组团成团
                                            if($isgroup['count']>=$res['group']){

                                                $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                                // p($group);

                                                //组团成团，一人中奖，全员中奖
                                                foreach ($group as $k => $v) {
                                                    $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                                }
                                            }else{
                                                $data3['status']=2;
                                                $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                // die;
                                $zcount=$res['count']-1;
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));
                                }
                            }else{
                                //筛选
                                // $orderProYes=[];
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);

                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $invuid=pdo_get('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'uid'=>$value['uid'],'gid'=>$value['gid']),array('invuid'));
                                    //是否组团
                                    if($invuid){ 
                                        $isgroup=pdo_get("yzcj_sun_group",array('uniacid'=>$_W['uniacid'],'invuid'=>$invuid['invuid'],'gid'=>$value['gid']),array('count(id) as count'));
                                        //判断是否组团成团
                                        if($isgroup['count']>=$res['group']){
                                            $group=pdo_getall('yzcj_sun_group',array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'invuid'=>$invuid['invuid']));
                                            //组团成团，一人中奖，全员中奖
                                            foreach ($group as $k => $v) {
                                                $res111=pdo_update('yzcj_sun_order',array('status'=>2),array('uniacid'=>$_W['uniacid'],'gid'=>$value['gid'],'uid'=>$v['uid']));    
                                            }
                                        }else{
                                            $data3['status']=2;
                                            $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                        }
                                    }else{
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    // $orderProNo=pdo_get("yzcj_sun_order",array('uniacid'=>$_W['uniacid'],''))

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid'],'status'=>1));

                                }
                            }
                        }
                    }
                    //抽奖码开奖
                    else if($res['state']==4){
                        // p($order);
                        $code=pdo_getall('yzcj_sun_code',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                        //打乱数组
                        shuffle($code);
                        if($res['one']==1){
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['onenum']-1+$res['twonum']+$res['threenum'];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];
                                // p($count);
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$count){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                // p($orderProYes);
                                $orderProNo=$code;
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                    }
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }else{
                            $ZorderPro=[];
                            //如果有指定中奖人的话
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $orderProYes=[];
                                $zcount=$res['count']-1;
                                // p($zcount);
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$zcount){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                $orderProNo=$code;

                                // p($orderProYes);
                                // p($orderProNo);
                                // die;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                            }else{
                                //筛选
                                $orderProYes=[];
                                foreach ($code as $key => $value) {
                                    if($key==0){
                                        array_push($orderProYes,$value);
                                        unset($code[$key]);
                                    }else{
                                        foreach ($orderProYes as $k => $v) {
                                            if($code[$key]['invuid']==$v['invuid']){
                                                unset($code[$key]);
                                            }
                                        }
                                        foreach ($orderProYes as $k => $v) {
                                            if(count($orderProYes)<$res['count']){
                                                if($code[$key]['invuid']!=$v['invuid']){
                                                    if($code[$key]){
                                                        array_push($orderProYes,$code[$key]);
                                                        unset($code[$key]);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }

                                $orderProNo=$code;
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {

                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {

                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('gid' =>$value['gid'],'uniacid'=>$_W['uniacid'],'uid'=>$value['invuid']));

                                }
                            }
                        }
                        
                    }
                    //普通开奖
                    else{
                        //打乱数组
                        shuffle($order);
                        if($res['one']==1){
                            $count=$res['onenum']-1+$res['twonum']+$res['threenum'];

                            $ZorderPro=[];
                            if($res['zuid']!=0){ 
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                //中奖处理
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$count);
                                $orderProNo=array_slice($ZorderPro,$count);
                                if($res['onenum']>1){
                                    $one=array_slice($orderProYes,0,$res['onenum']-1);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum']-1,$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']-1+$res['twonum']);
                                }

                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                                
                            }else{
                                $count=$res['onenum']+$res['twonum']+$res['threenum'];

                                //筛选
                                $orderProYes=array_slice($order,0,$count);
                                $orderProNo=array_slice($order,$count);
                                //中奖处理
                                if($res['onenum']>0){
                                    $one=array_slice($orderProYes,0,$res['onenum']);
                                }
                                if($res['twonum']>0){
                                    $two=array_slice($orderProYes,$res['onenum'],$res['twonum']);
                                }
                                if($res['threenum']>0){
                                    $three=array_slice($orderProYes,$res['onenum']+$res['twonum']);
                                }
                                if(!empty($one)){
                                    foreach ($one as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=1;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($two)){
                                    foreach ($two as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }
                                if(!empty($three)){
                                    foreach ($three as $key => $value) {
                                        $data3['status']=2;
                                        $data3['one']=3;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                    }
                                }

                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $data3['status']=4;
                                    $data3['one']=0;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$value['oid'],'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }else{
                            $ZorderPro=[];
                            if($res['zuid']!=0){
                                foreach ($order as $key => $value) {
                                    if($value['uid']==$res['zuid']){
                                        if($res['cid']==2){
                                            $userid=$value['uid'];
                                            $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                            $nmoney=$umoney+$res['gname'];
                                            $data4['money']=$nmoney;
                                            $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                        }
                                        $oid=$value['oid'];
                                        $data3['status']=2;
                                        $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                    }else{
                                        array_push($ZorderPro,$value);
                                    }
                                }
                                $zcount=$res['count']-1;
                                //筛选
                                shuffle($ZorderPro);
                                //筛选
                                $orderProYes=array_slice($ZorderPro,0,$zcount);
                                $orderProNo=array_slice($ZorderPro,$zcount);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }else{

                                //筛选
                                $orderProYes=array_slice($order,0,$res['count']);
                                $orderProNo=array_slice($order,$res['count']);
                                //随机中奖
                                foreach ($orderProYes as $key => $value) {
                                    if($res['cid']==2){
                                        $userid=$value['uid'];
                                        $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                                        $nmoney=$umoney+$res['gname'];
                                        $data4['money']=$nmoney;
                                        $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                                    }
                                    $oid=$value['oid'];
                                    $data3['status']=2;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                                //未中奖
                                foreach ($orderProNo as $key => $value) {
                                    $oid=$value['oid'];
                                    $data3['status']=4;
                                    $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                                }
                            }
                        }
                        
                    }

                    $info = array(
                        'num' => 10002
                    );
                    echo json_encode($info);
                }
                // if($res['accurate']<=$total){
                //     $data['status']=4;
                //     $data['endtime']=date("Y-m-d",time());
                //     //更改抽奖项目状态
                //     $result=pdo_update('yzcj_sun_goods', $data, array('gid' =>$gid,'uniacid'=>$_W['uniacid']));
                //     //获取参与了此次抽奖的用户
                //     $order=pdo_getall('yzcj_sun_order',array('uniacid'=>$_W['uniacid'],'gid'=>$gid));
                //     if($res['zuid']!=0){
                //         foreach ($order as $key => $value) {
                //             if($value['uid']==$res['zuid']){
                //                 if($res['cid']==2){
                //                     $userid=$value['uid'];
                //                     $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                //                     $nmoney=$umoney+$res['gname'];
                //                     $data4['money']=$nmoney;
                //                     $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                //                 }
                //                 $oid=$value['oid'];
                //                 $data2['status']=2;
                //                 $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                //             }else{
                //                 array_push($ZorderPro,$value);
                //             }
                //         }
                //         $zcount=$res['count']-1;
                //         //筛选
                //         shuffle($ZorderPro);
                //         $orderProYes=array_slice($ZorderPro,0,$zcount);
                //         $orderProNo=array_slice($ZorderPro,$zcount);
                //         //随机中奖
                //         foreach ($orderProYes as $key => $value) {
                //             if($res['cid']==2){
                //                 $userid=$value['uid'];
                //                 $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                //                 $nmoney=$umoney+$res['gname'];
                //                 $data4['money']=$nmoney;
                //                 $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                //             }
                //             $oid=$value['oid'];
                //             $data2['status']=2;
                //             $result1=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                //         }
                //         //未中奖
                //         foreach ($orderProNo as $key => $value) {
                //             $oid=$value['oid'];
                //             $data3['status']=4;
                //             $result1=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                //         }
                //     }else{
                //         //筛选
                //         shuffle($order);
                //         $orderProYes=array_slice($order,0,$res['count']);
                //         $orderProNo=array_slice($order,$res['count']);
                //         //随机中奖
                //         foreach ($orderProYes as $key => $value) {
                //             if($res['cid']==2){
                //                 $userid=$value['uid'];
                //                 $umoney=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$userid),'money')['money'];
                //                 $nmoney=$umoney+$res['gname'];
                //                 $data4['money']=$nmoney;
                //                 $result1=pdo_update('yzcj_sun_user',$data4, array('id' =>$userid,'uniacid'=>$_W['uniacid']));
                //             }
                //             $oid=$value['oid'];
                //             $data2['status']=2;
                //             $result2=pdo_update('yzcj_sun_order',$data2, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                //         }
                //         //未中奖
                //         foreach ($orderProNo as $key => $value) {
                //             $oid=$value['oid'];
                //             $data3['status']=4;
                //             $result2=pdo_update('yzcj_sun_order',$data3, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
                //         }
                //     }

                //     $info = array(
                //         'num' => 10002
                //     );
                //     echo json_encode($info);
                // }

            }
            $info = array(
                'num' => 10001,
                'res' => $res,
                'ad' => $ad1
            );
            // p($info);
            echo json_encode($info);
        }else{
            $info = array(
                'num' => 10001,
                'res' => $res,
                'ad' => $ad1
            );
            echo json_encode($info);
        }

    }
    //中奖结果
    public function doPageLuckyTicket(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $gid=$_GPC['gid'];
        $oid=pdo_get('yzcj_sun_order',array('uid'=>$uid,'uniacid'=>$_W['uniacid'],'gid'=>$gid),'oid')['oid'];
        $discount = pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'discount');
        // if($oid==null){
            
        // };
        $where="where a.oid="."'$oid' and a.uniacid=".$_W['uniacid'];
        $sql = "select a.*,b.*,a.status as status2 from ".tablename('yzcj_sun_order')."a join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid ".$where;
        $res = pdo_fetch($sql);  
        $res['code_img']='';
        // $res=$this->sliceArr($res);
        //判断是赞助商还是用户发起的抽奖
        if($res['sid']){
            $sid=$res['sid'];
            $sname=pdo_get('yzcj_sun_sponsorship',array('sid'=>$sid,'uniacid'=>$_W['uniacid']),array('sname','logo'));
            // p($sname);
            $res['name']=$sname['sname'];
            $res['logo']=$sname['logo'];
        }else{
            $uid1=$res['uid'];
            $name=pdo_get('yzcj_sun_user',array('id'=>$uid1,'uniacid'=>$_W['uniacid']),array('name','img'));
            $res['name']=$name['name'];
            $res['img']=$name['img'];
        }
            //中奖用户的头像和名字
            $sql1=pdo_fetchall("select a.one,b.name,b.img from ".tablename('yzcj_sun_order')."a join ".tablename('yzcj_sun_user')."b on b.id=a.uid where a.gid='$gid' and a.status=2  and a.uniacid=".$_W['uniacid']."||a.gid='$gid' and a.status=5 and a.uniacid=".$_W['uniacid']."||a.gid='$gid' and a.status=6 and a.uniacid=".$_W['uniacid']);
            
            foreach ($sql1 as $key => $value) {
                $sql1[$key]['name']=$this->emoji_decode($sql1[$key]['name']);
            };
            $res['nickName']=$sql1;
        
        // $res['0']['nickImg']=$sql1['img'];

        //参与人数
        $allnum=pdo_fetchcolumn("SELECT count(gid) FROM ".tablename('yzcj_sun_order')." where gid="."'$gid' and uniacid=".$_W['uniacid']);
        $res['allnum']=$allnum;
        //查询用户头像
        $uidArr=pdo_fetchall("select uid from ".tablename('yzcj_sun_order')." where gid = "."'$gid' and uniacid=".$_W['uniacid']);
        $img=[];
        foreach ($uidArr as $key => $value) {
            if(count($img)<6){
                $id=$value['uid'];
                $res1=pdo_fetch("select img from ".tablename('yzcj_sun_user')." where id="."'$id' and uniacid=".$_W['uniacid']);
                array_push($img,$res1);
            }
        }
        $res['imgArr']=$img;
        $res['discount']=$discount['discount'];

        //查询抽奖主图
        $cjzt=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'cjzt')['cjzt'];
        $res['cjzt']=$cjzt;
        // $info = array(
        //     'res' => $res,
        //     'nickName' => $sql1
        // );
        echo json_encode($res);
    }
    //折现
    public function doPageDiscount(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];
        $discount=$_GPC['discount'];
        $res=pdo_fetch("select a.uid,c.price from " . tablename('yzcj_sun_order'). "a left join ". tablename('yzcj_sun_goods')."b on b.gid=a.gid left join " . tablename('yzcj_sun_gifts')."c on c.id = b.giftId where a.oid = '$oid' and a.uniacid= " . $_W['uniacid']);
        
        $money=$res['price']*$discount;
        // p($money);
        $my=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'id'=>$res['uid']));
        $data['money']=$money+$my['money'];
        $res1=pdo_update('yzcj_sun_user',$data,array('uniacid'=>$_W['uniacid'],'id'=>$res['uid']));

        $data1['status']=5;
        $result1=pdo_update('yzcj_sun_order',$data1,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));


    }
    //地址管理 
    public function doPageMyAddr(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取用户id
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        //判断地址表内是否已有地址
        $isAddr=pdo_get('yzcj_sun_address',array('uid'=>$uid,'uniacid'=>$_W['uniacid']));
        //地址表内容
        $data['name']=$_GPC['userName'];
        $data['uid']=$uid;
        $data['telNumber']=$_GPC['telNumber'];
        $data['postalCode']=$_GPC['postalCode'];
        $data['provinceName']=$_GPC['provinceName'];
        $data['cityName']=$_GPC['cityName'];
        $data['countyName']=$_GPC['countyName'];
        $data['detailAddr']=$_GPC['detailInfo'];
        
        if(!$isAddr){
            $data['uniacid']=$_W['uniacid'];

            $res=pdo_insert('yzcj_sun_address',$data);
        }else{
            
            $res=pdo_update('yzcj_sun_address', $data, array('uid' =>$uid,'uniacid'=>$_W['uniacid']));
        }
    }
    //判断是否已有地址
    public function doPageShowAddr(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取用户id
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $gid=$_GPC['gid'];
        $oid=pdo_get('yzcj_sun_order',array('uid'=>$uid,'gid'=>$gid,'uniacid'=>$_W['uniacid']),'oid')['oid'];
        $address=pdo_getall('yzcj_sun_address',array('oid'=>$oid,'uniacid'=>$_W['uniacid']));
        echo json_encode($address);


    }
    //填写收货地址
    public function doPageGetAddr(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $gid=$_GPC['gid'];
      
        //获取用户id
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        
        $oid=pdo_get('yzcj_sun_order',array('uid'=>$uid,'gid'=>$gid,'uniacid'=>$_W['uniacid']),'oid')['oid'];

        //地址表内容
        if($_GPC['userName']!=undefined&&$_GPC['detailAddr']!=undefined){
            $data['uid']=$uid;
            $data['oid']=$oid;
            $data['name']=$_GPC['userName'];
            $data['telNumber']=$_GPC['telNumber'];
            $data['postalCode']=$_GPC['postalCode'];
            $data['provinceName']=$_GPC['provinceName'];
            $data['cityName']=$_GPC['cityName'];
            $data['countyName']=$_GPC['countyName'];
            $data['detailInfo']=$_GPC['detailInfo'];
            $data['detailAddr']=$_GPC['detailAddr'];
        }else if($_GPC['detailAddr']!=undefined&&$_GPC['userName']==undefined){
            $data['detailAddr']=$_GPC['detailAddr'];
        }else if($_GPC['detailAddr']==undefined&&$_GPC['userName']!=undefined){
            $data['uid']=$uid;
            $data['oid']=$oid;
            $data['name']=$_GPC['userName'];
            $data['telNumber']=$_GPC['telNumber'];
            $data['postalCode']=$_GPC['postalCode'];
            $data['provinceName']=$_GPC['provinceName'];
            $data['cityName']=$_GPC['cityName'];
            $data['countyName']=$_GPC['countyName'];
            $data['detailInfo']=$_GPC['detailInfo'];
        }else{
            $data['isDdfault']=0;
        }
        //判断地址表内是否已有地址
        $isAddr=pdo_get('yzcj_sun_address',array('oid'=>$oid,'uniacid'=>$_W['uniacid']));
        if(!$isAddr){
            $data['uniacid']=$_W['uniacid'];

            $res=pdo_insert('yzcj_sun_address',$data);
            $adid=pdo_insertid();
            $data1['adid']=$adid;
            $result=pdo_update('yzcj_sun_order',$data1, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
        }else{
            
            $res=pdo_update('yzcj_sun_address', $data, array('oid' =>$oid,'uniacid'=>$_W['uniacid']));
        }
      echo json_encode($oid);
    }
    //获取填写地址
    public function doPageGetAddress(){
        global $_GPC, $_W;
        $gid=$_GPC['gid'];
        // $adArr=pdo_getall('yzcj_sun_order',array('gid'=>$gid,'uniacid'=>$_W['uniacid'],'status'=>'2 || 6'));
        $adArr=pdo_fetchall('select * from '. tablename('yzcj_sun_order'). ' where gid='.$gid.' and  status = 2 and uniacid = '. $_W['uniacid'] . ' || gid='.$gid.' and  status =6 and uniacid = '.$_W['uniacid']);
        // p('select * from '. tablename('yzcj_sun_order'). ' where gid='.$gid.' and  status = 2 and uniacid = '. $_W['uniacid'] . ' || gid='.$gid.' and  status =6 and uniacid = '.$_W['uniacid']);
        $result=[];
        foreach ($adArr as $key => $value) {
            $adid=$value['adid'];
            $uid=$value['uid'];
            if(!empty($adid)){
                $res=pdo_fetchall("select a.*,b.img from ".tablename('yzcj_sun_address')."a join ".tablename('yzcj_sun_user')."b on b.id=a.uid where a.adid="."'$adid' and a.uniacid=".$_W['uniacid']);
            }else{
                $res=pdo_getall('yzcj_sun_user',array('id'=>$uid,'uniacid'=>$_W['uniacid']));
            }
            array_push($result,$res);
        }
        foreach ($result as $key => $value) {
            foreach ($value as $k => $v) {

                // foreach ($resNews as $key => $value) {
                    $v['name']=$this->emoji_decode($v['name']);
                    // p($result[$key][$k]['name']);
                // };
                // p($v);
                if($v['openid']){
                    $resNo[]=$v;
                }else if($v['adid']){
                    $resYes[]=$v;
                }
            }
        }
        $Info = array(
            'resNo' => $resNo,
            'resYes' => $resYes
        );
        echo json_encode($Info);
    }
    //获取首页公告
    public function doPageNew(){
        global $_GPC, $_W;
        $res=pdo_get('yzcj_sun_addnews',array('uniacid'=>$_W['uniacid'],'state'=>1));
        echo json_encode($res);
    }
    //常见问题
    public function doPageHelp(){
        global $_GPC, $_W;
        $res=pdo_getall('yzcj_sun_help',array('uniacid'=>$_W['uniacid']));
        echo json_encode($res);
    }
    //赞助介绍
    public function doPageGetSponsor(){
        global $_GPC, $_W;
        $res=pdo_get('yzcj_sun_sponsortext',array('uniacid'=>$_W['uniacid']));
        
        echo json_encode($res);
    }
    //申请赞助商
    public function doPageAddSponsor(){
        global $_GPC, $_W;
        $openid=$_GPC['openid'];
        //获取用户id
        $data['uid']=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $data['sname']=$_GPC['sname'];
        $data['phone']=$_GPC['phone'];
        $data['wx']=$_GPC['wx'];
        $data['status']=1;
        $data['uniacid']=$_W['uniacid'];

        $res=pdo_insert('yzcj_sun_sponsorship',$data);
        // p($data);
        echo json_encode($res);
    } 
    //赞助商申请状态查询
    public function doPageMySponsor(){
        global $_GPC, $_W;
        $openid=$_GPC['openid'];
        //获取用户id
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        $sid=pdo_get('yzcj_sun_sponsorship',array('uid'=>$uid,'uniacid'=>$_W['uniacid']));
        if(!empty($sid)){
            echo json_encode($sid);
        }else{
            echo json_encode($sid);
        }

    }
    //续费
    public function doPagerenewal(){
        global $_GPC, $_W;
        $sid=$_GPC['sid'];
        $data['status']=5;
        $res=pdo_update('yzcj_sun_sponsorship',$data,array('uniacid'=>$_W['uniacid'],'sid'=>$sid));
        // p($res);
        if($res){
            echo 1;
        }else{
            echo 2;
        }
    }
    // 拼接图片路径
    public function doPageUrl(){
        global $_GPC, $_W;
        echo $_W['attachurl'];
    }
    public function doPageUrl2(){
        global $_W, $_GPC;
        echo $_W['siteroot'];
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //余额
    public function doPageBalance(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $money=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']));
        $res=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $Info = array(
            'money' => $money['money'],
            'res' => $res
        );
        echo json_encode($Info);
    }
    //提现记录
    public function doPageGetBalance(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $user_id=$_GPC['uid'];

        $res=pdo_getall('yzcj_sun_withdrawal',array('user_id'=>$user_id,'uniacid'=>$_W['uniacid']));
        foreach ($res as $key => $value) {
            $res[$key][]=date('Y-m-d H:i:s',$value['time']);
            // array_push($res,date('Y-m-d H:i:s',$value['time']));
        }
        echo json_encode($res);
    }
    /**
     * 全部提现
     */
    public function doPageGoExtract(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $data['username']=$_GPC['wx'];//微信帐号
        $data['name']=$_GPC['name'];//真实姓名
        $data['user_id']=$_GPC['uid'];//用户ID
        $data['type']=2;//提现方式
        $data['uniacid'] = $_W['uniacid'];
        $data['tx_cost'] = $_GPC['money'];//提现金额
        if($_GPC['money']==1){
            $data['sj_cost']=1;
        }else{
            if($_GPC['sj_cost']<1){
                $data['sj_cost']=1;
            }else{
                $data['sj_cost'] = $_GPC['sj_cost'];//到账金额
            }
        }
        $data['time']=time();
        $data['state'] = 1;
        $res=pdo_insert('yzcj_sun_withdrawal',$data);
        
        $data1['money']=0;
        $result=pdo_update('yzcj_sun_user',$data1,array('openid' =>$openid,'uniacid'=>$_W['uniacid']));
        echo json_encode($data1['money']);
    }

    /*
     * 获取微信支付的数据
     *
     */
    public function doPageOrderarr() {
        global $_GPC,$_W;
        $openid = $_GPC['openid'];
        $appData = pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $appid = $appData['appid'];
        $mch_id = $appData['mchid'];
        $keys = $appData['wxkey'];
        $price = $_GPC['price'];
        $order_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $data = array(
            'appid' => $appid,
            'mch_id' => $mch_id,
            'nonce_str' => '5K8264ILTKCH16CQ2502SI8ZNMTM67VS',//
            //'sign' => '',
            'body' => time(),
            'out_trade_no' => date('Ymd') . substr('' . time(), -4, 4),
            'total_fee' => $price*100,
            'spbill_create_ip' => '120.79.152.105',
            'notify_url' => '120.79.152.105',
            'trade_type' => 'JSAPI',
            'openid' => $openid
        );
        ksort($data, SORT_ASC);
        $stringA = http_build_query($data);
        $signTempStr = $stringA . '&key='.$keys;
        $signValue = strtoupper(md5($signTempStr));
        $data['sign'] = $signValue;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $order_url);//如果不传这样进行设置
        curl_setopt($ch, CURLOPT_HEADER, 0);//header就是返回header头相关信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置数据是直接输出还是返回
        curl_setopt($ch, CURLOPT_POST, 1);//设置为post模式提交 跟 form表单的提交是一样
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->arrayToXml($data));//设置提交数据
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);//设置ssl不验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);//设置ssl不验证
        $result = curl_exec($ch);//执行请求 就等于html表单的 input:submit 如果没有设置 returntransfer 那么 是不会有返回值的 会直接输出
        curl_close($ch);//关闭
        $result = xml2array($result);
//        return $this->result(0,'',$result);
        echo json_encode($this->createPaySign($result));

    }
    function createPaySign($result)
    {
        global $_GPC,$_W;
        $appData = pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $keys = $appData['wxkey'];
        $data = array(
            'appId' => $result['appid'],
            'timeStamp' => (string)time(),
            'nonceStr' => $result['nonce_str'],
            'package' => 'prepay_id=' . $result['prepay_id'],
            'signType' => 'MD5'
        );
        ksort($data, SORT_ASC);
        $stringA = '';
        foreach ($data as $key => $val) {
            $stringA .= "{$key}={$val}&";
        }
        $signTempStr = $stringA . 'key='.$keys;
        $signValue = strtoupper(md5($signTempStr));
        $data['paySign'] = $signValue;
        return $data;
    }

    function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    //保存formid
    public function doPageSaveFormid(){
        global $_W, $_GPC;
      
        //判断是否参与过
        $Formid=pdo_get('yzcj_sun_userformid',array('gid'=>$_GPC['gid'],'user_id'=>$_GPC['user_id'],'uniacid'=>$_W['uniacid']));
        if(empty($Formid)){
            $data['user_id'] = $_GPC['user_id'];
            $data['form_id'] = $_GPC['form_id'];
            $data['openid'] = $_GPC['openid'];
            $data['gid'] = $_GPC['gid'];
            $data['state'] = $_GPC['state'];
            $data['time'] = date('Y-m-d H:i:s');
            $data['uniacid'] = $_W['uniacid'];
            $res = pdo_insert('yzcj_sun_userformid', $data);
            if ($res) {
                echo '1';
            } else {
                echo '2';
            }
        }
    }
    //保存formid
    public function doPageSaveFormid1(){
        global $_W, $_GPC;
      

            $data['user_id'] = $_GPC['user_id'];
            $data['form_id'] = $_GPC['form_id'];
            $data['openid'] = $_GPC['openid'];
            $data['gid'] = $_GPC['gid'];
            $data['state'] = $_GPC['state'];
            $data['time'] = date('Y-m-d H:i:s');
            $data['uniacid'] = $_W['uniacid'];
            $res = pdo_insert('yzcj_sun_userformid', $data);
            if ($res) {
                echo '1';
            } else {
                echo '2';
            }

    }
  //开奖模板消息
    //获取access_token
    public function doPageAccessToken(){
        global $_W, $_GPC;
        $res = pdo_get('yzcj_sun_system', array('uniacid' => $_W['uniacid']));
        $code = $_GPC['code'];
        $appid = $res['appid'];
        $secret = $res['appsecret'];
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$secret;
        function httpRequest($url,$data=null){
            $curl = curl_init();
            curl_setopt($curl,CURLOPT_URL,$url);
            curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
            curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);
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
    public function getaccess_token(){
        global $_W, $_GPC;
        $res=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']));
        $appid=$res['appid'];
        $secret=$res['appsecret'];
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$secret."";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
        $data = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($data,true);
        return $data['access_token'];
    }
    public function request_post($url, $data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
        $tmpInfo = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        if ($error) {
            return false;
        }else{
            return $tmpInfo;
        } 
    }
    //小程序码
    public function doPageGetwxCode(){
        global $_W, $_GPC;

        $access_token = $this->getaccess_token();
        $scene = $_GPC["scene"];
        $page = $_GPC["page"];
        $width = $_GPC["width"]?$_GPC["width"]:430;
        $auto_color = $_GPC["auto_color"]?$_GPC["auto_color"]:false;
        $line_color = $_GPC["line_color"]?$_GPC["line_color"]:'{"r":"0","g":"0","b":"0"}';
        $is_hyaline = $_GPC["is_hyaline"]?$_GPC["is_hyaline"]:false;

        $gid = intval($_GPC["gid"]);
        $uniacid = $_W["uniacid"];
        if($gid>0){
            //$goods = pdo_get('yzcj_sun_goods',array('uniacid'=>$uniacid,'gid'=>$gid),array("code_img"));
        }

        //$url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$access_token;
        $url = 'https://api.weixin.qq.com/wxa/getwxacode?access_token='.$access_token;
        
        //$data='{"scene":"'.$scene.'","page":"'.$page.'","width":"'.$width.'","is_hyaline":"'.$is_hyaline.'"}';
        //$data["scene"] = $scene;
        $data["path"] = $page;
        $data["width"] = $width;
        //$data["auto_color"] = $auto_color;
        //$data["line_color"] = $line_color;
        //$data["is_hyaline"] = $is_hyaline;
        //$data["is_hyaline"] = $is_hyaline;
        // $data ='{
        //     "scene": "'.$scene.'",
        //     "page": "'.$page.'",
        //     "width": "'.$width.'",
        //     "is_hyaline": "'.$is_hyaline.'"
        // }';
        $json_data = json_encode($data);
        //echo $json_data;exit;
        if(!empty($goods["code_img"])){
            $return = $goods["code_img"];
        }else{
            $return = $this->request_post($url,$json_data);
            //echo $return;exit;
            $res = pdo_update('yzcj_sun_goods',array("code_img"=>$return),array('uniacid'=>$uniacid,'gid'=>$gid));
        }

        //将生成的小程序码存入相应文件夹下
        $imgname = time().rand(10000,99999).'.jpg';
        //echo json_encode($return);exit;
        file_put_contents("../attachment/".$imgname,$return);
        
        echo json_encode($imgname);
    }

    public function doPageDelwxCode(){
        global $_W, $_GPC;
        $imgurl = $_GPC["imgurl"];
        $filename = '../attachment/'.$imgurl;
        if(file_exists($filename)){
            $info ='删除成功';
            unlink($filename);
        }else{
            $info ='没找到:'.$filename;
        }
        echo $info;
    }
    //手动开奖的模版消息
    public function doPageActiveMessage(){
        global $_W, $_GPC;
        //设置与发送模板信息

        $res = pdo_getall('yzcj_sun_userformid', array('uniacid' => $_W['uniacid'],'gid'=>$_GPC['gid'],'state'=>2));

        $template_id=pdo_get('yzcj_sun_sms',array('uniacid' => $_W['uniacid']),'tid1')['tid1'];
        // $gname=pdo_get('yzcj_sun_goods', array('uniacid' => $_W['uniacid'],'gid'=>$gid),'gname')['gname'];
        
        foreach ($res as $key => $value) {
            $result=$this->SendMessage($value['openid'],$value['gid'],$template_id,$_GPC['page'],$value['form_id'],$value['id'],$_GPC['access_token']);
        }
    }
    //curl请求函数，微信都是通过该函数请求
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

    //模版消息发送
    public function SendMessage($openid,$gid,$template_id,$page,$form_id,$id,$access_token){
        global $_W, $_GPC;
            
            $gname=pdo_get('yzcj_sun_goods', array('uniacid' => $_W['uniacid'],'gid'=>$gid),'gname')['gname'];
            $data_arr =array(
                'keyword1'  => array('value'=>$gname,'color'=>'black'),
                'keyword2'  => array('value'=>'您参与的抽奖正在开奖，点击查看详情','color'=>'red'),
            );
            $post_data = array (
                "touser"           => $openid,
                //用户的 openID，可用过 wx.getUserInfo 获取
                "template_id"      =>$template_id ,
                //小程序后台申请到的模板编号
                "page"             => $page,
                //点击模板消息后跳转到的页面，可以传递参数
                "form_id"          => $form_id ,
                //第一步里获取到的 formID
                "data"             => $data_arr,
            );
            // p($post_data);
            $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$access_token;
            //这里替换为你的access_token
            // function send_post( $url, $post_data ) {
            //     $options = array(
            //         'http' => array(
            //             'method'  => 'POST',
            //             'header'  => 'Content-type:application/json',
            //             //header 需要设置为 JSON
            //             'content' => $post_data,
            //             'timeout' => 60
            //             //超时时间
            //         )
            //     );

            //     $context = stream_context_create( $options );
            //     $result = file_get_contents( $url, false, $context );

            //     return $result;
            // }
                
            $json_data = json_encode($post_data);//转化成json数组让微信可以接收
            $res = $this->https_request($url, urldecode($json_data));//请求开始
            $res = json_decode($res, true);
            if ($res['errcode'] == 0 && $res['errcode'] == "ok") {
                echo "发送成功！<br/>";
            }

            
            // $data = json_encode($post_data, true);
            // //将数组编码为 JSON
            // // p($data);
            // $return = send_post( $url, $data);
            
            // echo '返回值：' . $return;
            // //这里的返回值是一个 JSON，可通过 json_decode() 解码成数组
            // //用完后，就删掉。
            // $result=pdo_delete('yzcj_sun_userformid',array('id'=>$id));
    }
    //自动开奖的模版消息
    public function doPageAutoMessage(){
        global $_W, $_GPC;
        //设置与发送模板信息

        $res = pdo_getall('yzcj_sun_userformid', array('uniacid' => $_W['uniacid'],'gid'=>$_GPC['gid'],'state'=>2));
        $template_id=pdo_get('yzcj_sun_sms',array('uniacid' => $_W['uniacid']),'tid1')['tid1'];
        foreach ($res as $key => $value) {
            
            $result=$this->SendMessage($value['openid'],$_GPC['gid'],$template_id,$_GPC['page'],$value['form_id'],$value['id'],$_GPC['access_token']);
        }
        
    }
    //发货通知，模版消息
    public function doPageDeliveryMessage(){
        global $_W, $_GPC;
        $oid=$_GPC['oid'];
        $where="where a.uniacid=".$_W['uniacid'] . " and a.oid='$oid'";
        $sql = "select a.*,b.gname,c.* from ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join ".tablename('yzcj_sun_address')."c on c.adid=a.adid ".$where;
        $result = pdo_fetch($sql);
        // $result['code_img']='';
        $form = pdo_get('yzcj_sun_userformid', array('uniacid' => $_W['uniacid'],'gid'=>$result['gid'],'state'=>1));
        
        $template_id=pdo_get('yzcj_sun_sms',array('uniacid' => $_W['uniacid']),'tid2')['tid2'];

        $data_arr =array(
            'keyword1'  => array('value'=>$result['orderNum'],'color'=>'black'),
            'keyword2'  => array('value'=>$result['gname'],'color'=>'red'),
            'keyword3'  => array('value'=>$result['provinceName'].$result['cityName'].$result['countyName'].$result['detailInfo'].$result['detailAddr'].$result['postalCode'],'color'=>'red'),
            'keyword4'  => array('value'=>$result['name'],'color'=>'red'),
            'keyword5'  => array('value'=>$result['telNumber'],'color'=>'red'),
            'keyword6'  => array('value'=>'礼物已发货，请您注意查收！','color'=>'red'),
        );
        // echo json_encode($data_arr);
        $post_data = array (
            "touser"           => $form['openid'],
            //用户的 openID，可用过 wx.getUserInfo 获取
            "template_id"      =>$template_id ,
            //小程序后台申请到的模板编号
            "page"             => $_GPC['page'],
            //点击模板消息后跳转到的页面，可以传递参数
            "form_id"          => $form['form_id'] ,
            //第一步里获取到的 formID
            "data"             => $data_arr,
        );
        // echo json_encode($post_data);
        $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$_GPC['access_token'];

        $json_data = json_encode($post_data);//转化成json数组让微信可以接收
        $res = $this->https_request($url, urldecode($json_data));//请求开始
        $res = json_decode($res, true);
        // echo json_encode($res);
        if ($res['errcode'] == 0 && $res['errcode'] == "ok") {
            echo "发送成功！<br/>";
        }
    }
    //模版消息发送
    // public function SendDeliveryMessage($openid,$gid,$template_id,$page,$form_id,$id,$access_token){
    //     global $_W, $_GPC;
            
    //         $gname=pdo_get('yzcj_sun_goods', array('uniacid' => $_W['uniacid'],'gid'=>$gid),'gname')['gname'];
    //         $data_arr =array(
    //             'keyword1'  => array('value'=>$gname,'color'=>'black'),
    //             'keyword2'  => array('value'=>'您参与的抽奖正在开奖，点击查看详情','color'=>'red'),
    //         );
    //         $post_data = array (
    //             "touser"           => $openid,
    //             //用户的 openID，可用过 wx.getUserInfo 获取
    //             "template_id"      =>$template_id ,
    //             //小程序后台申请到的模板编号
    //             "page"             => $page,
    //             //点击模板消息后跳转到的页面，可以传递参数
    //             "form_id"          => $form_id ,
    //             //第一步里获取到的 formID
    //             "data"             => $data_arr,
    //         );
    //         // p($post_data);
    //         $url = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=".$access_token;
 
    //         $json_data = json_encode($post_data);//转化成json数组让微信可以接收
    //         $res = $this->https_request($url, urldecode($json_data));//请求开始
    //         $res = json_decode($res, true);
    //         if ($res['errcode'] == 0 && $res['errcode'] == "ok") {
    //             echo "发送成功！<br/>";
    //         }

    // }

////////////////////////////////////////////quanzi///////////////////////////////////////////////
/**
     * 求两个已知经纬度之间的距离,单位为米
     *
     * @param lng1 $ ,lng2 经度
     * @param lat1 $ ,lat2 纬度
     * @return float 距离，单位米
     */
    function getdistance($lng1, $lat1, $lng2, $lat2) {
        // 将角度转为弧度
        // p($lat2);
        // p($lng2);
        $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
        
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
        return $s;
    }
    //全部文章
    public function doPageShowCircle(){
        global $_W, $_GPC;
        $openid=$_GPC['openid'];
        $index=$_GPC['index'];
        $typeId=$_GPC['type'];
        if($_GPC['longitude_dq']!=undefined){
            $longitude_dq=$_GPC['longitude_dq'];//当前用户的经度
            $latitude_dq=$_GPC['latitude_dq'];//当前用户的纬度.
        }
        $where ="where a.uniacid=".$_W['uniacid'] . " and a.status=2";
        if($typeId!=null&&$typeId!=undefined){
            if($typeId!=0){
                $where .=" and a.type =".$typeId;
            }
        }
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $type=pdo_getall('yzcj_sun_selectedtype',array('uniacid'=>$_W['uniacid']));

        $sql = "select a.*,b.name,b.img as avatarUrl,c.tname from ".tablename('yzcj_sun_circle')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid left join ".tablename('yzcj_sun_selectedtype')." c on c.id = a.type ".$where." ORDER BY a.time desc";
        $res = pdo_fetchall($sql);
        $love=[];
        $con=[];
        $time=[];
        $distance=[];
        foreach ($res as $key => $value) {
            $id=$value['id'];
            //查询点赞人数
            $lovenum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_praise')." where cid="."'$id' and uniacid=".$_W['uniacid']);
            $res[$key]['lovenum']=$lovenum;
            //热门，按点赞来排
            $love[]=$res[$key]['lovenum'];
            $lovestate=pdo_get('yzcj_sun_praise',array('uid'=>$uid,'cid'=>$id,'uniacid'=>$_W['uniacid']));
            if($lovestate){
                $res[$key]['lovestate']=true;
            }else{
                $res[$key]['lovestate']=false;
            }
            $res[$key]['img']= explode(',',$res[$key]['img']);  
            //查询评论人数
            $conmmentnum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_content')." where cid="."'$id' and uniacid=".$_W['uniacid']);
            $res[$key]['conmmentnum']=$conmmentnum;
            //热门，按评论来排
            $con[]=$res[$key]['conmmentnum'];
            //时间最近
            $res[$key]['shijian']=strtotime($value['time']);
            $time[]=$res[$key]['shijian'];
            //按距离
            if($_GPC['longitude_dq']!=undefined){
                $res[$key]['juli']=round(($this->getdistance($longitude_dq,$latitude_dq,$value['longitude'],$value['latitude']))/1000,1);
                $distance[]=$res[$key]['juli'];
            }
        }
        if($index==0){

            array_multisort($con,SORT_DESC,$love,SORT_DESC,$res);
            // array_multisort($love,SORT_DESC,$res);
        }else if($index==1){

            array_multisort($time,SORT_DESC,$res); 
        }else if($index==2){
            if($_GPC['longitude_dq']!=undefined){
                array_multisort($distance,SORT_ASC,$res);
            }
        }
        // $res['type']=$type;
        $info=array(
            'res'=>$res,
            'type'=>$type,
        );
        echo json_encode($info);
    }
    
    //我的动态
    public function doPageShowMyCircle(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        $where="where a.uniacid=".$_W['uniacid'] . " and a.status=2 and a.uid=$uid";
        $sql = "select a.*,b.name,b.img as avatarUrl,c.tname from ".tablename('yzcj_sun_circle')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid left join ".tablename('yzcj_sun_selectedtype')." c on c.id = a.type ".$where." ORDER BY a.time desc";
        $res = pdo_fetchall($sql);
        $where1="where a.uniacid=".$_W['uniacid'] . " and a.status=1 and a.uid=$uid";
        $sql1 = "select a.*,b.name,b.img as avatarUrl from ".tablename('yzcj_sun_circle')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid ".$where1." ORDER BY a.time desc";
        $res1 = pdo_fetchall($sql1);
        foreach ($res as $key => $value) {
            $id=$value['id'];
            //查询点赞人数
            $lovenum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_praise')." where cid="."'$id' and uniacid=".$_W['uniacid']);
            $res[$key]['lovenum']=$lovenum;
            $lovestate=pdo_get('yzcj_sun_praise',array('uid'=>$uid,'cid'=>$id,'uniacid'=>$_W['uniacid']));
            if($lovestate){
                $res[$key]['lovestate']=true;
            }else{
                $res[$key]['lovestate']=false;
            }
            $res[$key]['img']= explode(',',$res[$key]['img']);  
            //查询点赞人数
            $conmmentnum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_content')." where cid="."'$id' and uniacid=".$_W['uniacid']);
            $res[$key]['conmmentnum']=$conmmentnum;
        }
        foreach ($res1 as $key => $value) {
            $id=$value['id'];
            $res1[$key]['img']= explode(',',$res1[$key]['img']);  
        }
        $info=array(
            'res'=>$res,
            'res1'=>$res1,
        );
        echo json_encode($info);

    }
    //点赞
    public function doPageDelParise(){
        global $_W, $_GPC;
        $openid=$_GPC['openid'];
        $cid=$_GPC['id'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $lovestate=pdo_get('yzcj_sun_praise',array('uid'=>$uid,'cid'=>$cid,'uniacid'=>$_W['uniacid']));
        if($lovestate){
            $res=pdo_delete('yzcj_sun_praise',array('id'=>$lovestate['id'],'uniacid'=>$_W['uniacid']));
        }else{
            $data['uid']=$uid;
            $data['cid']=$cid;
            $data['uniacid']=$_W['uniacid'];
            $res=pdo_insert('yzcj_sun_praise',$data);
        }
        echo $res;
    }
    //删除文章
    public function doPageDelCircle(){
        global $_W, $_GPC;
        $cid=$_GPC['id'];

        $res=pdo_delete('yzcj_sun_circle',array('id'=>$cid,'uniacid'=>$_W['uniacid']));

    }
    //详情
    public function doPageCircleDetail(){
        global $_W, $_GPC;
        $openid=$_GPC['openid'];
        $cid=$_GPC['id'];
        //获取ID
        $uid=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];

        $where="where a.uniacid=".$_W['uniacid']." and a.id='$cid'";
        $sql = "select a.*,b.name,b.img as avatarUrl,c.tname from ".tablename('yzcj_sun_circle')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid left join ".tablename('yzcj_sun_selectedtype')." c on c.id=a.type ".$where;
        $res = pdo_fetch($sql);

        //查询点赞人数
        $lovenum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_praise')." where cid="."'$cid' and uniacid =".$_W['uniacid']);
        $res['lovenum']=$lovenum;
        $lovestate=pdo_get('yzcj_sun_praise',array('uid'=>$uid,'cid'=>$cid,'uniacid'=>$_W['uniacid']));
        if($lovestate){
            $res['lovestate']=true;
        }else{
            $res['lovestate']=false;
        }
        $res['img']= explode(',',$res['img']);  
        //查询点赞人数
        $conmmentnum=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_content')." where cid="."'$cid' and uniacid =".$_W['uniacid']);
        $res['conmmentnum']=$conmmentnum;

        //评论
        $where1="where a.uniacid=".$_W['uniacid']." and a.cid='$cid'";
        $sql1 = "select a.*,b.name,b.img as avatarUrl from ".tablename('yzcj_sun_content')."a left join ".tablename('yzcj_sun_user')."b on b.id=a.uid ".$where1;
        $res1 = pdo_fetchall($sql1);

        $info = array(
            'res' => $res,
            'res1' => $res1,
        );
        echo json_encode($info);

    }
    //发送评论
    public function doPagePutContent(){
        global $_W, $_GPC;
        $openid=$_GPC['openid'];
        //获取ID
        $data['uid']=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $data['cid']=$_GPC['id'];
        $data['content']=$_GPC['content'];
        $data['uniacid']=$_W['uniacid'];
        $res=pdo_insert('yzcj_sun_content',$data);
    }
    //发表文章
    public function doPageSendCircle(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        //获取ID
        $data['uid']=pdo_get('yzcj_sun_user',array('openid'=>$openid,'uniacid'=>$_W['uniacid']),'id')['id'];
        $data['content']=$_GPC['content'];
        $data['type']=$_GPC['type'];
        if($_GPC['uname']!=undefined){
            $data['uname']=$_GPC['uname'];
        }
        if($_GPC['uphone']!=undefined){
            $data['uphone']=$_GPC['uphone'];
        }
        if($_GPC['addr']!=undefined){
            $data['addr']=$_GPC['addr'];
        }
        // $data['uphone']=$_GPC['uphone'];
        // $data['addr']=$_GPC['addr'];
        $data['latitude']=$_GPC['latitude'];
        $data['longitude']=$_GPC['longitude'];
        $data['status']=pdo_get('yzcj_sun_system',array('uniacid'=>$_W['uniacid']),'is_zx')['is_zx'];
        $data['uniacid']=$_W['uniacid'];
        $res=pdo_insert('yzcj_sun_circle',$data);
        $id=pdo_insertid();

        echo json_encode($id);
    }
    //获取分类
    public function doPageCircleType(){
        global $_GPC,$_W;
        $res=pdo_getAll('yzcj_sun_selectedtype',array('uniacid'=>$_W['uniacid']));
        echo json_encode($res);
    }
    //图片上传
    public function doPageToupload1(){
        global $_GPC,$_W;
        $id = $_GPC["id"];
        $uptypes=array(  
            'image/jpg',  
            'image/jpeg',  
            'image/png',  
            'image/pjpeg',  
            'image/gif',  
            'image/bmp',  
            'image/x-png'  
        );  
        $max_file_size=2000000;     //上传文件大小限制, 单位BYTE  
     
        $destination_folder="../attachment/"; //上传文件路径  
        $imgpreview=1;      //是否生成预览图(1为生成,其他为不生成);  
        $imgpreviewsize=1/2;    //缩略图比例 
        if (!is_uploaded_file($_FILES["file"]['tmp_name']))  
        //是否存在文件  
        {  
         echo "图片不存在!";  
         exit;  
        }
       $file = $_FILES["file"];
       if($max_file_size < $file["size"])
        //检查文件大小  
       {
        echo "文件太大!";
        exit;
       }
      if(!in_array($file["type"], $uptypes))  
        //检查文件类型
      {
        echo "文件类型不符!".$file["type"];
        exit;
      }
      if(!file_exists($destination_folder))
      {
        mkdir($destination_folder);
      }  
      $filename=$file["tmp_name"];  
      $image_size = getimagesize($filename);  
      $pinfo=pathinfo($file["name"]);  
      $ftype=$pinfo['extension'];  
      $destination = $destination_folder.str_shuffle(time().rand(111111,999999)).".".$ftype;  
      if (file_exists($destination) && $overwrite != true)  
      {  
        echo "同名文件已经存在了";  
        exit;  
      }  
      if(!move_uploaded_file ($filename, $destination))  
      {  
        echo "移动文件出错";  
        exit;
      }
      $pinfo=pathinfo($destination);  
      $fname=$pinfo['basename'];  

        $newimg = $fname;
        //获取数据库图片
        $img = pdo_getcolumn("yzcj_sun_circle", array('id' => $id,'uniacid'=>$_W['uniacid']), 'img');
        //$img = pdo_getcolumn($tablearr[$types], array('id' => $tcid), 'img');
        if($img){
            $data["img"] = $img.",".$newimg;
        }else{
            $data["img"] = $newimg;
        }
        $res=pdo_update("yzcj_sun_circle",$data,array('id'=>$id,'uniacid'=>$_W['uniacid']));
        echo json_encode($res);
        echo $fname;
        @require_once (IA_ROOT . '/framework/function/file.func.php');
        @$filename=$fname;
        @file_remote_upload($filename); 
    }

    //送礼页面首页
    public function doPageAllGifts(){
        global $_GPC,$_W;
        //类型
        $type=pdo_getall('yzcj_sun_type',array('uniacid'=>$_W['uniacid']));
        //轮播图
        $giftsbanner=pdo_get('yzcj_sun_giftsbanner',array('uniacid'=>$_W['uniacid']));

        //分割图片
        foreach ($type as $key => $value) {
            $gifts=pdo_getall('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'type'=>$value['id'],'status'=>2,'count >'=>0),array('id','gname','price','lottery','pic'));
            foreach ($gifts as $k => $v) {
                $v['pic']= explode(',',$v['pic']);
                $gifts[$k]['img']=$v['pic'];
            }
            $type[$key]['gifts']=$gifts;
        }
        $where="where a.uniacid=".$_W['uniacid'] ." and b.count>0 and b.status=2";
        $daily=pdo_fetchall("SELECT a.*,b.`id` as gid,b.`gname`,b.`price`,b.`lottery`,b.`pic` FROM ".tablename('yzcj_sun_daily'). " a"  . " left join " . tablename("yzcj_sun_gifts") . " b on b.id=a.gid ".$where." ORDER BY a.id asc");
        //分割图片
        foreach ($daily as $key => $value) {
            $value['pic']= explode(',',$value['pic']);
            $daily[$key]['img']=$value['pic'];
        }

        $info=array(
            'type'=>$type,
            'giftsbanner'=>$giftsbanner,
            'daily'=>$daily,
        );

        echo json_encode($info);    
    }
    //送礼页面详情
    public function doPageGiftsDetail(){
        global $_GPC,$_W;
        $id=$_GPC['id'];
        // $gifts=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$id));
        $where="where a.uniacid=".$_W['uniacid'] ." and id='$id'";
        $gifts=pdo_fetch("SELECT a.*,b.`sname` FROM ".tablename('yzcj_sun_gifts'). " a"  . " left join " . tablename("yzcj_sun_sponsorship") . " b on b.sid=a.sid ".$where." ORDER BY a.id asc");
        //分割图片
        $gifts['pic']= explode(',',$gifts['pic']);
        // $daily[$key]['img']=$value['pic'];

        echo json_encode($gifts);
    }
    //进入后台
    public function doPageadminLogin(){
        global $_GPC,$_W;
        $openid=$_GPC['openid'];
        $uid=pdo_get('yzcj_sun_user',array('uniacid'=>$_W['uniacid'],'openid'=>$openid),'id')['id'];
        $sid=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'uid'=>$uid,'status'=>2),'sid')['sid'];
        if(empty($sid)){
            echo 0;
        }else{
            echo json_encode($sid);
        }
    }
    //后台首页
    public function doPageAdminIndex(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        //实物抽奖
        $waitorder1=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =2 and b.cid=1 and a.uniacid=".$_W['uniacid']);
        $yiorder1=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =6 and b.cid=1 and a.uniacid=".$_W['uniacid']);
        $completeorder1=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =5 and b.cid=1 and a.uniacid=".$_W['uniacid']);
        //礼物
        $waitorder=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =2 and a.uniacid=".$_W['uniacid']);
        $yiorder=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =6 and a.uniacid=".$_W['uniacid']);
        $completeorder=pdo_fetchcolumn("SELECT count(oid) FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =5 and a.uniacid=".$_W['uniacid']);

        $shelves=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_gifts')." where sid="."'$sid' and status =2 and uniacid=".$_W['uniacid']);
        $noshelves=pdo_fetchcolumn("SELECT count(id) FROM ".tablename('yzcj_sun_gifts')." where sid="."'$sid' and status =1 and uniacid=".$_W['uniacid']);

        $sponsor=pdo_get('yzcj_sun_sponsorship',array('uniacid'=>$_W['uniacid'],'sid'=>$sid));

        $info=array(
            'waitorder'=>$waitorder,
            'yiorder'=>$yiorder,
            'completeorder'=>$completeorder,
            'waitorder1'=>$waitorder1,
            'yiorder1'=>$yiorder1,
            'completeorder1'=>$completeorder1,
            'shelves'=>$shelves,
            'noshelves'=>$noshelves,
            'sponsor'=>$sponsor,
        );
        echo json_encode($info);

    }
    //后台礼物订单管理
    public function doPageAdminOrder(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        $waitorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =2 and a.uniacid=".$_W['uniacid']." ORDER BY a.adid desc");
        $waitorder=$this->sliceArr($waitorder);
        $yiorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =6 and a.uniacid=".$_W['uniacid']);
        $yiorder=$this->sliceArr($yiorder);
        $completeorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img,c.* FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =5 and a.uniacid=".$_W['uniacid']);
        $completeorder=$this->sliceArr($completeorder);

        $info=array(
            'waitorder'=>$waitorder,
            'yiorder'=>$yiorder,
            'completeorder'=>$completeorder,
        );
        echo json_encode($info);
    }
    //后台抽奖订单管理
    public function doPageAdminOrder1(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        $waitorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =2 and b.cid=1 and a.uniacid=".$_W['uniacid']." ORDER BY a.adid desc");
        $waitorder=$this->sliceArr($waitorder);
        $yiorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =6 and b.cid=1 and a.uniacid=".$_W['uniacid']);
        $yiorder=$this->sliceArr($yiorder);
        $completeorder=pdo_fetchall("SELECT a.*,b.*,b.`pic` as img FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =5 and b.cid=1 and a.uniacid=".$_W['uniacid']);
        $completeorder=$this->sliceArr($completeorder);
        $info=array(
            'waitorder'=>$waitorder,
            'yiorder'=>$yiorder,
            'completeorder'=>$completeorder,
        );
        echo json_encode($info);
    }
    //订单详情
    public function doPageOrderdetail(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];
        $order=pdo_fetch("SELECT a.*,a.`status` as statuss,b.*,c.`name`,c.`telNumber`,c.`countyName`,c.`detailAddr`,c.`provinceName`,c.`cityname`,c.`detailInfo`,c.`postalCode`,d.`price` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_address')."c on c.adid=a.adid left join".tablename('yzcj_sun_gifts')."d on d.id=b.giftId where a.oid="."'$oid' and a.uniacid=".$_W['uniacid']);
        $order['code_img']='';
        echo json_encode($order);
    }
    //发货处理
    public function doPagedelivery(){
        global $_GPC,$_W;
        $oid=$_GPC['oid'];
        $data['status']=6;
        $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$oid));
    }
    //一键发货
    public function doPagedoSdelivery(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        $oid=pdo_fetchall("SELECT a.`oid` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid left join".tablename('yzcj_sun_gifts')."c on c.id=b.giftId where c.sid="."'$sid' and a.status =2 and a.adid!='' and a.uniacid=".$_W['uniacid']);
        if(!empty($oid)){
            foreach ($oid as $key => $value) {
                $data['status']=6;
                $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$value['oid']));
            }
            echo json_encode($oid);
        }else{
            echo 0;
        }
        
    }
    //一键发货
    public function doPagedoSdelivery1(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        $oid=pdo_fetchall("SELECT a.`oid` FROM ".tablename('yzcj_sun_order')."a left join ".tablename('yzcj_sun_goods')."b on b.gid=a.gid where b.sid="."'$sid' and a.status =2 and a.adid!='' and a.uniacid=".$_W['uniacid']);
        // p($oid);
        if(!empty($oid)){
            foreach ($oid as $key => $value) {
                $data['status']=6;
                $res=pdo_update('yzcj_sun_order',$data,array('uniacid'=>$_W['uniacid'],'oid'=>$value['oid']));
            }
            echo json_encode($oid);
        }else{
            echo 0;
        }
        
    }
    //后台礼物管理
    public function doPageAdminGift(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        //上架
        $res1=pdo_fetchall("SELECT * FROM ".tablename('yzcj_sun_gifts')." where sid="."'$sid' and status =2 and uniacid=".$_W['uniacid']);
        //下架
        $res2=pdo_fetchall("SELECT * FROM ".tablename('yzcj_sun_gifts')." where sid="."'$sid' and status =1 and uniacid=".$_W['uniacid']);
        //遍历
        foreach ($res1 as $key => $value) {
            $res1[$key]['img']=explode(',',$res1[$key]['pic']);
            $id=$value['id'];
            $num = pdo_getall("yzcj_sun_goods", array('uniacid'=>$_W['uniacid'],'giftId' => $id));
            // var_dump($id);
            foreach ($num as $k => $v) {

                $sum += $v['count'];

            }
            $res1[$key]['num']=$sum;

        }

        foreach ($res2 as $key => $value) {
            $res2[$key]['img']=explode(',',$res2[$key]['pic']);
            $id=$value['id'];
            $num = pdo_getall("yzcj_sun_goods", array('uniacid'=>$_W['uniacid'],'giftId' => $id));
            foreach ($num as $k => $v) {
                $sum +=$v['count'];
            }
            $res2[$key]['num']=$sum;
        }
        

        $info=array(
            'res1'=>$res1,
            'res2'=>$res2,
        );
        echo json_encode($info);
    }
    //上架
    public function doPageuse(){
        global $_GPC,$_W;
        $id=$_GPC['id'];
        $data['status']=2;
        $res=pdo_update('yzcj_sun_gifts',$data,array('uniacid'=>$_W['uniacid'],'id'=>$id));
    }
    //下架
    public function doPagenoUse(){
        global $_GPC,$_W;
        $id=$_GPC['id'];
        $data['status']=1;
        $res=pdo_update('yzcj_sun_gifts',$data,array('uniacid'=>$_W['uniacid'],'id'=>$id));
    }
    //一键上下架
    public function doPagedoNoUse(){
        global $_GPC,$_W;
        $sid=$_GPC['sid'];
        $status=$_GPC['status'];
        if($status==1){
            $data['status']=1;
            $res=pdo_update('yzcj_sun_gifts',$data,array('uniacid'=>$_W['uniacid'],'sid'=>$sid));
        }else{
            $data['status']=2;
            $res=pdo_update('yzcj_sun_gifts',$data,array('uniacid'=>$_W['uniacid'],'sid'=>$sid));
        }
    }
    //补充货源
    public function doPageaddNum(){
        global $_GPC,$_W;
        $id=$_GPC['id'];
        $count=$_GPC['count'];
        $oldCount=pdo_get('yzcj_sun_gifts',array('uniacid'=>$_W['uniacid'],'id'=>$id),'count')['count'];
        $data['count']=$oldCount+$count;
        $res=pdo_update('yzcj_sun_gifts',$data,array('uniacid'=>$_W['uniacid'],'id'=>$id));
    }
}