##
# 需要先评教

class UpcCrawler::CrapRequiredError < UpcCrawler::Error
  def initialize
    super '需要先评教'
  end
end