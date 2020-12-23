<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\cache\driver\Redis;

class Miaosha extends Controller
{

	private $expire = 43200;	//redis缓存过期时间  12h
	private $redis = null;
	private $cachekey = null;	//缓存变量名
	private $basket = [];		//私有数组，存放商品信息

	private $store = 50;

	/**
	 * 购物车初始化，传入用户id
	 */
	public function __construct()
	{
		parent::__construct();

		$this->redis = new \Redis();		// 实例化
		$this->redis->connect('127.0.0.1','6379');
		$this->redis->auth('zxf123456');

		
	}

	/**
	 * 秒杀初始化
	 */
	public function Ms_init()
	{
		// 删除缓存列表
		$this->redis->del($this->cachekey);

		$len = $this->redis->llen($this->cachekey);
		$count = $this->store - $len;

		for ($i=0; $i < $count; $i++) { 

			// 向库存列表推进50个,模拟50个商品库存
			$this->redis->lpush($this->cachekey,1);
		}

		echo "库存初始化完成:".$this->redis->llen($this->cachekey);
	}
 

	/**
	 * 秒杀入口
	 */
	public function index()
	{
		$id = 1;	//商品编号
		
		if (empty($id)) {
			// 记录失败日志
			return $this->writeLog(0,'商品编号不存在');	
		}

		// 计算库存列表长度
		$count = $this->redis->llen($this->cachekey);

		// 先判断库存是否为0,为0秒杀失败,不为0,则进行先移除一个元素,再进行数据库操作
		if ($count == 0) {	//库存为0

			$this->writeLog(0,'库存为0');
			echo "库存为0";
			exit;

		}else{
			// 有库存
			//先移除一个列表元素
			$this->redis->lpop($this->cachekey);

			$ordersn = $this->build_order_no();	//生成订单
			$uid = rand(0,9999);	//随机生成用户id
			$status = 1;
			// 再进行数据库操作
			$data = Db::table('ab_goods')->field('count,amount')->where('id',$id)->find();	//查找商品

			if (!$data) {
				return $this->writeLog(0,'该商品不存在');
			}

			$insert_data = [
				'order_sn' => $ordersn,
				'user_id' => $uid,
				'goods_id' => $id,
				'price'	=> $data['amount'],
				'status' => $status,
				'addtime' => date('Y-m-d H:i:s')
			];

			// 订单入库
			$result = Db::table('ab_order')->insert($insert_data);
			// 自动减少一个库存
			$res = Db::table('ab_goods')->where('id',$id)->setDec('count');

			if ($res) {
				echo "第".$count."件秒杀成功";
				$this->writeLog(1,'秒杀成功');
			}else{
				echo "第".$count."件秒杀失败";
				$this->writeLog(0,'秒杀失败');
			}
		}
	}

	/**
	 * 生成订单号
	 */
	public function build_order_no()
	{
		return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
	}

	/**
	 * 生成日志  1成功 0失败
	 */
	public function writeLog($status = 1,$msg)
	{
		$data['count'] = 1;
		$data['status'] = $status;
		$data['addtime'] = date('Y-m-d H:i:s');
		$data['msg'] = $msg;
		return Db::table('ab_log')->insertGetId($data);
	}

	public function sqlMs()
	{
		$id = 1;	//商品编号

		$count = 50;
		$ordersn = $this->build_order_no();	//生成订单
		$uid = rand(0,9999);	//随机生成用户id
		$status = 1;
		// 再进行数据库操作
		$data = Db::table('ab_goods')->field('count,amount')->where('id',$id)->find();	//查找商品

		// 查询还剩多少库存
		$rs = Db::table('ab_goods')->where('id',$id)->value('count');
		if ($rs <= 0) {
			
			$this->writeLog(0,'库存为0');
		}else{

			$insert_data = [
				'order_sn' => $ordersn,
				'user_id' => $uid,
				'goods_id' => $id,
				'price'	=> $data['amount'],
				'status' => $status,
				'addtime' => date('Y-m-d H:i:s')
			];

			// 订单入库
			$result = Db::table('ab_order')->insert($insert_data);
			// 自动减少一个库存
			$res = Db::table('ab_goods')->where('id',$id)->setDec('count');

			if ($res) {
				echo "第".$data['count']."件秒杀成功";
				$this->writeLog(1,'秒杀成功');
			}else{
				echo "第".$data['count']."件秒杀失败";
				$this->writeLog(0,'秒杀失败');
			}
		}
	}

}