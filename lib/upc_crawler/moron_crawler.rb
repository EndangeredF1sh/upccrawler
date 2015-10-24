class UpcCrawler::MoronCrawler

  APP_ID = 1180

  def initialize(agent)
    @agent = agent
    @agent.post('http://211.87.177.1/jwxt/Logon.do?method=logonBySSO')
  end

  def scores
    return @scores if defined? @scores

    @scores = []
    @scores_array = []

    p = @agent.get('http://211.87.177.1/jwxt/xszqcjglAction.do?method=queryxscj')
    tmp = p.search('#PageNavigation').to_s
    m = tmp.match(/value="\d*?\/(\d*)"/)
    if m
      pages = m[1].to_i
    else
      pages = 1
    end

    1.upto(pages) do |page|
      p = @agent.post('http://211.87.177.1/jwxt/xszqcjglAction.do?method=queryxscj', 'PageNum' => page.to_s) if page>1
      p.search('#mxh tr').each do |line|
        # 备注：引号里面不是空格
        data = line.search('td').collect { |cell| cell.text.sub(' ', '') }
        @scores_array << data
        @scores << {
            id: data[0],
            stu_id: data[1],
            name: data[2],
            term: data[3],
            course: data[4],
            score: data[5],     # 因为有的成绩是等级制
            score_flag: data[6],
            course_category: data[7],
            course_type: data[8],
            phase: data[9].to_i,
            credit: data[10].to_f,
            exam_type: data[11],
            refresh_term: data[12]
        }
      end
    end

    @scores
  end

  def scores_array
    return @scores_array if defined? @scores_array
    scores
    @scores_array
  end

  def scores_by_terms(*terms)
    scores.select { |s| terms.include? s[:term] }
  end

  def class_tables(term = nil)

  end
end