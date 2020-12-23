**<h3> ThinkPHP5+Redis实现商品秒杀 </h3>**

环境：wamp，redis，php安装redis扩展

秒杀功能大致思路：获取缓存列表的长度，如果长度（llen）等于0，就停止秒杀，即秒杀失败，如果长度大于0，则继续运行，先从缓存中移除一个元素（lpop）,再进行数据库操作（添加订单表，商品库存数量减一），如果再进一个人秒杀，就再走一遍流程，循环往复。

<h3>一、安装Redis扩展</h3>
1. 查看PHP版本信息

打开phpinfo.php，查看PHP版本,我的是PHP7.3.4，还有一个需要注意  **Architecture   x64**
![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222161749864-1129309878.png)
2.下载扩展文件

https://pecl.php.net/package/redis   

https://pecl.php.net/package/igbinary

根据自己环境，选择合适的版本

3. 解压

解压下载的压缩包，并把php_redis.dll、php_redis.pdb和php_igbinary.dll、php_igbinary.pdb四个文件，移至自己PHP版本对应目录下的ext文件夹下 E:\phpstudy_pro\Extensions\php\php7.3.4nts\ext

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222164807145-1393133863.png)
![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222164742011-984277540.png)

4.修改php.ini
添加如下代码：

extension=php_igbinary.dll

extension=php_redis.dll

如果有这两句可以把前面的分号删掉，没有就自己添加上，要注意顺序，php_igbinary.dll 要在 php_redis.dll 前面
![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222162515629-1499462028.png)

5.重启Apache
重启后，再运行phpinfo.php，查看是否安装成功
![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222162719709-423593920.png)

<h3>二、数据结构</h3>
一共三张表，ab_goods商品表，ab_order订单表，ab_log日志表

商品表

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222163042866-461493414.png)

订单表
![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222163113911-476145242.png)

日志表   记录秒杀信息

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222163135126-1337317952.png)

<h3>三、代码</h3>
代码路径：index/miaosha/index

<h3>四、压力测试</h3>
使用apache压力测试工具 AB 测试，模拟多用户秒杀商品，模拟60秒内发起3000个请求，并发600次，秒杀50个库存商品

AB测试相关参数说明

- -r 指定接收到错误信息时不退出程序
- -t 等待响应的最大时间
- -n 指定压力测试总共的执行次数
- -c 用于指定压力测试的并发数

　1.初始化50个库存，运行ms_init方法

　2.测试   命令行：E:\phpstudy_pro\Extensions\Apache2.4.39\bin>ab -r -t 60 -n 3000 -c 1000 http://gouwuche.zxf/index/miaosha/index  

需要先进入apache的bin目录下执行命令

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222164003369-1018506403.png)

　　3.检测数据库数据

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222164312081-1467081165.png)

![](https://img2020.cnblogs.com/blog/1149706/202012/1149706-20201222164328512-345737144.png)

日志表状态为1（秒杀成功）的数据有50人，订单表里的订单数也是50条，商品表里的商品数量变成了0（测试之前是50），商品秒杀成功完成！

如果不用redis而是直接用mysql的话，商品表订单的数量count会变成负数，而秒杀成功的人数也多余50人，订单表里的订单数量也多余50条（新测），下面是直接用Mysql的例子；

    public function sqlMs()
    {
        $id = 1;    //商品编号

        $count = 50;
        $ordersn = $this->build_order_no();    //生成订单
        $uid = rand(0,9999);    //随机生成用户id
        $status = 1;
        // 再进行数据库操作
        $data = Db::table('ab_goods')->field('count,amount')->where('id',$id)->find();    //查找商品

        // 查询还剩多少库存
        $rs = Db::table('ab_goods')->where('id',$id)->value('count');
        if ($rs <= 0) {
            
            $this->writeLog(0,'库存为0');
        }else{

            $insert_data = [
                'order_sn' => $ordersn,
                'user_id' => $uid,
                'goods_id' => $id,
                'price'    => $data['amount'],
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