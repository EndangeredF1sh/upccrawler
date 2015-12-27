##
# 教务系统崩溃

class UpcCrawler::MoronIsFuckedError < UpcCrawler::Error
  def initialize
    super '教务系统崩溃'
  end
end