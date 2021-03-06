# UpcCrawler (Archieved)

## 原作者 (vjudge0) 停止开发并删库，本fork作存档保留。

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

因为教务系统经常崩溃，所以代码在发现教务系统进不去之后会引发 MoronIsFuckedError 异常；此外，如果未完成评教，则会引发 CrapRequiredError 异常，不过可以通过本脚本快速评教（所有老师除最后一项是 B 以外其余选项全是 A）。

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
kcxz     | 课程性质，除非搞特殊研究，否则你肯定想留空
kcmc     | 课程名称，一般留空
xsfs     | 如果只希望显示最好成绩，则为“zhcj”，否则应该留空

成绩当然不只一页，所以要通过扒到页面的内容确定到底有多少页，然后遍历。因为教务系统本身就很慢，所以查成绩真的是一件很耗时间的事儿（在教务系统上使用默认设置的话，50门课大约需要十多秒）。

需要注意的是，如果未进行评教，那么发送请求之后只能得到一段包含“评教未完成，不能查询成绩！”（标点符号全角）字样的 JavaScript 代码，并拒绝显示。

### 课表

#### 自己的课表

在登录到教务系统之后，通过以下链接获取自己的课表：

    http://211.87.177.1/jwxt/tkglAction.do?method=goListKbByXs&istsxx=no&xnxqh=#{term}&zc=#{week}

其中`#{term}`是学期，格式类似 2014-2015-1，`#{week}`是周数，可以省略。

需要注意的是，如果未进行评教，那么发送请求之后只能得到一段包含“评教未完成，不能课表查看！”（标点符号全角）字样的 JavaScript 代码，并拒绝显示。

#### 别人的课表

**注意，每学期临近期末但不到期末的时候，教务处会因为下学期排课而关掉相关功能！**

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

＊需要注意的是，从 14 级开始，学生学号被加密了，所以需要先通过 `http://211.87.177.1/jwxt/ggxx/selectStu_xsxx.jsp` 把学生查出来，扒下加密后的 ID。

#### 查询某教室

**注意，每学期临近期末但不到期末的时候，教务处会因为下学期排课而关掉相关功能！**

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
2. 如果教师或谁通过合法手段占用了教室，查到的课表上面会有特殊文字表示教室已被占用。这段文字的格式与普通课程不同。
3. 怎样知道学校都有哪些教室呢？对以下地址发送 POST 请求：


    http://211.87.177.1/jwxt/ggxx/selectJs.jsp?id=classroomID&name=classname&type=1

其中 POST 参数只有一个：PageNum，表示页码。通过分析表格中的链接即可获取 ID。

#### 注意事项

关于课表还需要注意以下几个问题：

1. 一个格子里可能有不只一门课
2. 有些课是连着上的，例如连着上一下午，这个时候单元格会被合并。
3. 上课周次有以下几种情况：“1-9周”、“1,4-6,8周”、“1-13单周”等。分析时要注意判断。

### 选课

### 教学评价

课程分为“理论课”、“实践课”（或者叫“实验课”，就差一个字）和“体育课”。为了保险，需要把每个都评价一下，不过方法都是一样的。

#### 进入评价页面

由于组合框数据被散列了，所以不管能否评教，都需要先访问：

    http://211.87.177.1/jwxt/jiaowu/jxpj/jxpjgl_queryxs.jsp

需要确定评价批次、评价分类和评价课程类别的具体值。它们分属三个组合框，无 id，其 name 分别为 pjpc、pjfl、pjkc。如果评教未开始，那么 pjpc 组合框中应该只有“---请选择---”一项。

为了方便，这里直接给出对应的正则表达式和参考值（2015 年 11 月）。如果将来的评教仍然是这些值，那么就不用正则匹配了：

* 评价批次
    * 理论课（如果未出现则表示还没评教）：`<option value="(.*?)">.*?理论课<\/option>`（参考值 E6EAF3B5D3E347AAB7993FF7C2574136）
    * 实践课（可能不存在，或者叫“实验课”）：`<option value="(.*?)">.*?实践课<\/option>`（无参考值）
    * 体育课（别忘了）：`<option value="(.*?)">.*?体育课<\/option>`（参考值 CE3147774ACB48A384E34F71FB1AB77C）
* 评价分类：`<option value="(.*?)">学生评教<\/option>`（参考值 F8207E3060DA45A3AB495788334C66BA）
* 评价课程类别：
    * 理论课：`<option value="(.*?)">理论课程<\/option>`（参考值 C96FD3FC03074117A35BF8FB146A6BAE）
    * 实践课（或者叫实验课）：`<option value="(.*?)">实践课程<\/option>`（参考值 8753C4C55EE142D991C9FB9660D2B94A）
    * 体育课：`<option value="(.*?)">体育课程<\/option>`（参考值 1324AAB17D284CA0BF2CECBA7977F540）
    * 这三个紧挨着，可以只用一个正则表达式提取。

通过扒下这些 value，可以对以下地址发送 POST 请求，查看任课老师列表：

    http://211.87.177.1/jwxt/jxpjgl.do?method=queryJxpj&type=xs

POST 数据如下：

键       | 值
---------|-----------------------------
xnxq     | 学年学期，类似 2015-2016-1
pjpc     | 评价批次，需要扒数据（分别有理论课、体育课，可能有实验课）
pjfl     | 评价分类，扒到什么就是什么（只有“学生评教”）
pjkc     | 评价课程，需要扒数据（应该和 pjpc 对应上）
PageNum  | 页码

接下来将看到我们敬爱的老师们。注意一般情况下一页是装不下的，分页方法和成绩单一模一样。

总页码包含在`<input type="hidden" name = "totalPages" value="2">`中。

只要按照上面 POST 格式提交即可得到每一页的老师。

#### 逐个评价

只要教师后面的有效链接不是“查看”就说明能够评价（否则是已经评完了）。

我们要扫描每个“评价”和“修改”链接：`<a href='javascript:void(0);'" onclick="javascript:JsMods('/jwxt/jxpjgl.do?method=......',1000,530,300,300);return false; ">评价</a>`和`<a href='javascript:void(0);'" onclick="javascript:           JsMods('/jwxt/jxpjgl.do?method=......',1000,530,300,300);return false; ">修改</a>`

记住这个链接（自己手动添加`http://211.87.177.1`），后面还要使用。

打开这个链接，提取里面的选项按钮。当然是代表评教了。

提取方法（注意，Xh可能是小写，并且后面不一定是两个空格）：

    <input type="radio" name="radio1" radioXh="0"  value="xxxxxx">A非常好

为了不被有关部门茶水表，不要把每个选项都选得一样！例如可以这样：

    <input type="radio" name="radio1" radioXh="1"  value="xxxxxx">B较好

除了第一项选 B 以外其他都选 A。

然后对以下地址发送 POST 请求：

    http://211.87.177.1/jwxt/jxpjgl.do?method=savePj&tjfs=2&val=#{选项}

选项：其内容为若干个选项（option）的值（value），并且用“*”连接。也就是说评教结果既要写到 POST 内容中，也要写到 URL 上面。

POST 数据如下：

键          | 值
------------|-------------------------------
从链接中分离  | 能够从链接中分离出来的值，除了method和xnxqid
typejsxs    | xs
isxytj      | 1
radio1...   | 提取出来的数据

**注意！POST 头的 Referer 应该是抓到的链接地址，否则评价失败！**

#### 附

评教分数即使提交之后也能改：只要进到“查看”页面，正常评教，然后到控制台里来一句`saveData(2)`，然后确定即可。

## 一卡通

一卡通网站的 AppID 为 24406。在登录到数字石大之后，执行`login_to_app 24406`，即可有一卡通网站的访问权。

一卡通基本信息：

    http://card.upc.edu.cn/CardManage/CardInfo/BasicInfo

消费记录，只需要发送GET请求：

    http://card.upc.edu.cn/CardManage/CardInfo/TrjnList?beginTime=#{开始时间}&endTime=#{结束时间}&type=1

因为充钱需要验证码所以……

## 图书馆

图书馆的 AppID 为 1186。然后去`http://211.87.177.4/reader/book_lst.php`扒数据就行了，反正没有人在乎这个东西。

需要注意的是，因为不同教学单位的信息提供情况不同，所以不要从这个网站获取个人信息（专业班级和<s>让人垂涎三尺的</s>身份证号等）。

## 照片

照片未采取任何安全措施，所以只要知道学号，就可以通过

    http://211.87.177.1/jwxt/uploadfile/studentphoto/pic/#{学号}.JPG

直接下载（扩展名大写）。不需要登录，只需要你的 IP 没被 block（也就是在校内）。

因为全都是证件照，所以不要指望从这里找到美女。<s>石油大学只有恐龙。</s>
