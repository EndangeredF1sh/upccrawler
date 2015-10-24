UpcCrawler
===

一个扒数字石大查课表查成绩查！@＃¥％……&的应用。

感谢数字石大不设验证码（虽然输错三次会出现但是可以绕过去），要不然事情会变得很恶心。

过去拿PHP写的（感觉naive），后来用Ruby改写，不过急着统计成绩，所以只完成了查成绩的部分。

感谢石油高中的培养，从周一到周天都有课，所以目前没心思继续开发了。

# 部署

很抱歉这还不是一个Gem包所以各位看官要稍微麻烦一下了。

因为是用 Ruby 2 写的，所以不知道 Ruby 1.9 能否正常运行。

需要一个 gem 包：

    sudo gem install mechanize

getscore.rb 是一个示例程序，专门爬成绩的。

# 数字石大接口

只有一个查成绩，对吧？阅读以下内容后，你自己就可以去查别的东西了：

`lib/upc_crawler.rb`，里面有个 `login_to_app`，它就是通过数字石大登录到其他网站的接口。

## 到底是怎么登录的

首先要有一个账号密码（废话）。

别急着登录，首先要获取一个会话：

	http://cas.upc.edu.cn/cas/login?service=http%3A%2F%2Fi.upc.edu.cn%2Fdcp%2Findex.jsp

记住这个会话（因为里面有防跨站机制），然后再把账号密码填上，提交表单。因为 Mechanize 包把这些事自动做好了，所以想知道具体网址的话可以自己去审查元素。

之后就进去了。如果想登录到一个具体的应用，例如教务系统，首先通过审查元素，获取链接里面的ID，然后对以下链接发送 GET 请求：

	http://i.upc.edu.cn/dcp/forward.action?path=dcp/core/appstore/menu/jsp/redirect&ac=3&appid=#{app_id}

其中`#{app_id}`就是你通过“审查元素”找到的ID。然后你会获取一个带有SESSIONID的“CAS认证转向”页面。追着页面里的链接走，直到页面标题不再是“CAS认证转向”为止。然后，其他应用就登录成功了。

## 教务系统

教务系统的AppID是1180。

教务系统叫`Moron`，至于为什么叫这个，请查字典……

因为教务系统经常崩溃，所以代码在发现教务系统进不去之后会引发 MoronIsFuckedError。

（其实叫Idiot也不错:ghost:）

### 成绩

代码已经实现了一部分。

在登录到教务系统之后，对以下链接发送 POST 请求即可查到成绩

	http://211.87.177.1/jwxt/xszqcjglAction.do?method=queryxscj
	
POST 参数如下。这些参数都可以省略：

键       | 值
---------|--------------
PageNum  | 页码
kksj     | 开课时间（2014-2015-1）
ok       | （留空）
kcxz     | 课程性质*
kcmc     | 课程名称*
xsfs     | 显示方式*

*：具体取值我没研究，所以可以自己到教务系统的页面上审查元素。

成绩当然不只一页，所以要通过扒到页面的内容确定到底有多少页，然后遍历。因为教务系统本身就很慢，所以查成绩真的是一件很耗时间的事儿（在教务系统上使用默认设置的话，50门课大约需要十多秒）。

### 课表

#### 自己的课表

在登录到教务系统之后，通过以下链接获取自己的课表：

    http://211.87.177.1/jwxt/tkglAction.do?method=goListKbByXs&istsxx=no&xnxqh=#{term}&zc=#{week}

其中`#{term}`是学期，格式类似 2014-2015-1，`#{week}`是周数，可以省略。

#### 别人的课表

对以下链接发出 POST 请求：

    http://211.87.177.1/jwxt/tkglAction.do?method=goListKb&type=16&zc=#{week}

其中 #{week} 是周数。

POST 内容如下：

键       | 值
---------|------------
usertype | 2
xnxqh    | 学期
abc      | 1
type     | 16
findType | cx
type2    | 1
xs0101id | 学号＊

需要注意的是，从14级开始，学生学号被加密了，所以我也不知道怎么处理。

#### 查询某教室

对以下地址发送 POST 请求：

    http://211.87.177.1/jwxt/tkglAction.do?method=goListKb&type=16&zc=#{term}

POST 内容如下：

键          | 值
------------|-------------
xnxqh       | 学期
abc         | 1
type        | 4
findType    | cx
type2       | 1
classroomID | 教室ID

教室ID需要实现通过教务系统的“查找教室”对话框，配合审查元素来获取。

通过获取本学期所有教室的上课情况，就可以总结出自习教室的空闲情况了。

注意：

1. 有些教室的课表并不能从教务系统上查到。
2. 如果教师或谁通过合法手段占用了教室，查到的课表上面会有特殊文字表示教室已被占用。
3. 怎样知道学校都有哪些教室呢？对以下地址发送 POST 请求：

http://211.87.177.1/jwxt/ggxx/selectJs.jsp?id=classroomID&name=classname&type=1

其中 POST 参数只有一个：PageNum，表示页码。通过分析表格中的链接即可获取 ID。

#### 注意事项

关于课表还需要注意以下几个问题：

1. 一个格子里可能有不只一门课
2. 有些课是连着上的，例如连着上一下午，这个时候单元格会被合并。

## 一卡通

一卡通网站的 AppID 为 24406。在登录到数字石大之后，执行`login_to_app 24406`，即可有一卡通网站的访问权。

一卡通基本信息：

    http://card.upc.edu.cn/CardManage/CardInfo/BasicInfo

消费记录，只需要发送GET请求：

    http://card.upc.edu.cn/CardManage/CardInfo/TrjnList?beginTime=#{开始时间}&endTime=#{结束时间}&type=1

因为充钱需要验证码所以……

## 图书馆

图书馆的 AppID 为 1186。然后去`http://211.87.177.4/reader/book_lst.php`扒数据就行了，反正没有人在乎这个东西。
