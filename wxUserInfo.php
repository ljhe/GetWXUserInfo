<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/27
 * Time: 13:56
 */
class wxUserInfo
{
    protected $appId = '';
    protected $appSecret = '';

    public function __construct($name)
    {
        if ($name == 'cqll') {
            $this->appId = 'wxcefda74365138670';
            $this->appSecret = 'd9b789b08929ad66c8e2d8bc9c0d7c06';
            $this->getAccessToken($this->appId,$this->appSecret);
        }
        elseif ($name == 'myll'){
            $this->appId = 'wx06fa5c832337f919';
            $this->appSecret = '7c5b6eb0284a25fcb75bc6f5ffab6153';
            $this->getAccessToken($this->appId,$this->appSecret);
        }
        else{
            echo '参数错误';
        }
    }

    /**
     * 获取access_token
     * @param $appId
     * @param $appSecret
     */
    private function getAccessToken($appId,$appSecret)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appId."&secret=".$appSecret;

        //$result = $this->httpGet($url);
        $result = json_decode(file_get_contents($url),true);

        if (!isset($result['access_token'])) {
            echo '获取access_token失败';
            return;
        }

        $this->getUserList($result['access_token']);
    }

    /**
     * 获取用户的openId
     * @param $access_token
     * @param string $nextOpenId
     * @param int $sum
     * @param string $tmp
     * @return array|mixed|string
     */
    private function getUserList($access_token,$nextOpenId = '',$sum = 0,$tmp = '')
    {
        //$result = $this->httpGet($url);

        if ($nextOpenId == '') {
            $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$access_token;
            $result = json_decode(file_get_contents($url),true);
        }else{
            $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=".$access_token."&next_openid=".$nextOpenId;
            $result = json_decode(file_get_contents($url),true);

            /**
             * PATCH $tmp 为空的时候使用 array_merge_recursive 方法会报数据类型错误
             */
            if ($tmp == '') {
                $tmp = $result;
            }else{
                $tmp = array_merge_recursive($result,$tmp);
            }
        }

        if ($result['total'] == 0 || !isset($result['total'])) {
            echo '暂无人关注';
            return;
        }

        // 如果总关注人数比当前拉取的人多,递归调用
        $sum += $result['count'];
        if ($result['total'] > $sum) {
            $this->getUserList($access_token,$result['next_openid'],$sum,$tmp);
        }else{
            $this->makeData($access_token,$result);
            return;
        }
        $this->makeData($access_token,$tmp);
        return;
    }


    /**
     * 拼接批量查询所需的 json 格式
     * @param $access_token
     * @param $data
     */
    private function makeData($access_token,$data)
    {
        // 拼接为批量查询的数组格式
        $openIdArray = array_column($data,'openid');
        $tmp = '';
        $i = 0;

        foreach ($openIdArray[0] as $k){
            $tmp[$i]['openid'] = $k;
            $tmp[$i]['lang'] = 'zh_CN';
            $i++;
        }

        $this->getUserInfo($access_token,$tmp);
    }

    /**
     * 获取用户的详细信息
     * @param $access_token
     * @param $tmp
     * @param string $data
     */
    private function getUserInfo($access_token,$tmp,$data = '')
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=".$access_token;
        $sum = count($tmp);
        // 批量查询最多一次100条,递归调用
        if ($sum>100) {
            // 每次取100条
            $res['user_list'] = array_slice($tmp,0,100);
            // 每次总的数据去除100条
            $tmp = array_slice($tmp,100);
            // 发送POST请求获取信息
            $thisData = $this->httpGetUserInfo($res,$url);
            // 初始值为空，转化成数组
            if ($data == '') {
                $data = json_decode($thisData,true);
            }
            // data值不为空，将新老数组合并
            else{
                $data = array_merge_recursive(json_decode($thisData,true),$data);
            }
            $this->getUserInfo($access_token,$tmp,$data);
        }else{
            $res['user_list'] = $tmp;
            $thisData = $this->httpGetUserInfo($res,$url);
            if ($data == '') {
                $data = json_decode($thisData,true);
            }else{
                $data = array_merge_recursive(json_decode($thisData,true),$data);
            }
        }

        $this->makeCsv($data);
    }


    /**
     * 发送获取用户请求
     * @param $res
     * @param $url
     * @return bool|string
     */
    private function httpGetUserInfo($res,$url)
    {
        $res = json_encode($res);
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'content-type:application/json:charset=utf-8',	//头部说明说带参数格式为json
                'content' => $res
            )
        );

        $context = stream_context_create($opts);
        $response = file_get_contents($url, false, $context);
        return $response;
    }

    /**
     * curl 使用 get 发送微信相关接口的请求，使用 CURLOPT_SSL_VERIFYPEER 设置为 false 可防止 curl 请求结果一片空白
     * @param $url
     * @return mixed
     */
    private function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    // 是否验证对等证书
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);    // 设置等待时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);          // 设置cURL允许执行的最长秒数
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * 生成文档
     * @param $data
     */
    public function makeCsv($data)
    {
        // 表格的表头信息
        $tableheader = array('序号', 'openid', '性别');
        $tablelength = count($tableheader);

        // 表格数据
        /*$data = array(
            array('1', 'x', '0'),
            array('2', 'x', '1'),
            array('3', 'x', '2'),
            array('4', 'x', '3'),
        );*/

        // 把获取的完整的用户信息修改为所需信息
        $data = $data['user_info_list'];
        $tmp = '';
        foreach ($data as $k => $v){
            $tmp[$k][0] = $k;
            $tmp[$k][1] = $v['openid'];
            if ($v['sex'] == 0) {
                $tmp[$k][2] = $v['sex'];
            }elseif ($v['sex'] == 1){
                $tmp[$k][2] = '男';
            }else{
                $tmp[$k][2] = '女';
            }
        }

        /*输入到CSV文件 解决乱码问题*/
        $html = "\xEF\xBB\xBF";

        /*输出表头*/
        foreach ($tableheader as $value) {
            $html .= $value . "\t ,";
        }
        $html .= "\n";

        /*输出内容*/
        foreach ($tmp as $value) {
            for ($i = 0; $i < $tablelength; $i++) {
                $html .= $value[$i] . "\t ,";
            }
            $html .= "\t ,";
            $html .= "\n";
        }

        /*输出CSV文件*/
        header("Content-type:text/csv");
        header("Content-Disposition:attachment; filename=截止".date("Y-m-d",time())."用户信息.csv");
        echo $html;
        exit();
    }
}