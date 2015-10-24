require 'rubygems'
require 'mechanize'
require 'digest'

##
# UpcCrawler 是一个用于爬数字石大，从而获取一卡通、教务系统等信息的库。
#
# == 示例
#
#   require 'upc_crawler'
#
#   # 数字石大账号密码
#   begin
#     agent = UpcCrawler.new('1501010101', '123456')
#   rescue LoginError
#     puts '账号密码错误'
#   end
#
#   ## 强智教务系统（moron）
#   begin
#     # 查成绩
#     scores = agent.moron.scores
#     # 查自己课表
#     # 查某班级课表
#     # 查某教师课表
#     # 查某教室课表
#     # 查某课程课表
#     # 查某人课表
#   rescue MoronIsFuckedError
#     # 因为教务系统经常挂掉，所以建议在编写查询工具时加入缓存机制
#     puts '教务系统又挂了'
#   end
#
#
# == 报告 Bug
#
# 如果发现问题，可以到 GitHub 中报告：
#
# https://github.com/vjudge1/upccrawler/issues
#
# == 感谢
#
# 感谢学校搞了一个单点登录而且不设验证码。

class UpcCrawler

  ##
  # 版本号

  VERSION = '0.0.1'

  class Error < RuntimeError
  end

  ##
  # 建立一个实例
  #
  # 需要给出数字石大账号和密码
  #
  def initialize(username, password)
    @username = username
    @password = password

    @login = false
    @agent = Mechanize.new

    # 仅仅是用来装 13 的
    #yield self if block_given?
  end

  ##
  # 登录
  #
  # 登录到数字石大，从而继续爬数据。
  #
  # 需要注意的是，其实你不必手动调用这个函数，因为在爬数据的时候程序会自动判断。

  def login
    return true if @login

    @agent.get('http://cas.upc.edu.cn/cas/login?service=http%3A%2F%2Fi.upc.edu.cn%2Fdcp%2Findex.jsp') do |login_page|
      i_page = login_page.form_with(name: 'login_form') do |form|
        form.username = @username
        form.password = Digest::MD5.hexdigest(@password)
      end.submit

      if i_page.search('#login_form').to_s.match('错误的用户名或密码')
        raise LoginError.new('数字石大')
      end

      @login = true
    end
  end

  #def logout

  #end

  ##
  # 是否已经登录

  def is_login?
    @login
  end

  ##
  # 强智教务系统
  def moron
    @moron if defined? @moron
    login
    begin
      login_to_app MoronCrawler::APP_ID
      @moron = MoronCrawler.new(@agent)
    rescue
      raise MoronIsFuckedError.new
    end
  end

  private

  def login_to_app(app_id)
    cas = @agent.get("http://i.upc.edu.cn/dcp/forward.action?path=dcp/core/appstore/menu/jsp/redirect&ac=3&appid=#{app_id}")
    while cas.title == 'CAS认证转向'
      url = cas.links.first.href
      cas = @agent.get(url)
    end
  end
end

# 个人用
require_relative 'upc_crawler/login_error'
require_relative 'upc_crawler/moron_crawler'
require_relative 'upc_crawler/moron_is_fucked_error'