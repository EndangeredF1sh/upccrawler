##
# 账号密码错误

class UpcCrawler::LoginError < UpcCrawler::Error

  attr_reader :source

  def initialize(source)
    @source = source
    super "#{source}的账号密码错误"
  end
end