# 欢迎使用 日天脚手架(funkyday)
**基于swoole4+开发的高性能http协议接口框架**
![Pandao editor.md](https://test-cdn.dacallapp.com/2003/18/5c3aeaee004012000403.jpeg?x-oss-process=image/resize,m_lfit,h_150,w_150 "Pandao editor.md")
## 开始使用
**本脚手架基于swoole扩展开发,常驻进程,相较于传统php的fpm管理fastcgi的方式大幅减少系统文件io操作**
### 安装
#### debian
执行命令：`./install.sh`
### 路由规则
架构会根据路由在根目录下api文件夹内找到对应的类以及函数名
https://{host}/api/{group}/{controller}/{method}
### 控制器 
控制器默认在根目录下的api文件夹内创建
<pre><code>
    namespace api\open;
    class test{
        public function test(){
            //业务逻辑
        }
    }
</code></pre>
### 模型
### 数据库
### 缓存
### 自定义命令
### 计划任务
### 参与修改 
| 版本号        | 参与人     |  
| --------   | -----  | 
| v1.0.0     | 日天侠  |
| v1.3.3     | 日天侠、一万熊  |
| v2.0.0     | 一万熊  |