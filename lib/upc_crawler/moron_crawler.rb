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

    if p.search('script').to_s.include?('评教未完成')
      raise UpcCrawler::CrapRequiredError.new
    end

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

  def auto_judge(term = nil)
    # 首先确定能否评价，然后挨个批次分类遍历
    p = @agent.post('http://211.87.177.1/jwxt/jiaowu/jxpj/jxpjgl_queryxs.jsp')

    # 进入评价页面
    page_html = p.search('form').to_s

    lilun1   = page_html.match(/value="(.*?)">.*?理论课</)[1]
    lilun2   = page_html.match(/value="(.*?)">理论课程</)[1]
    shijian1 = page_html.match(/value="(.*?)">.*?实.课</)[1]
    shijian2 = page_html.match(/value="(.*?)">实.课程</)[1]
    tiyu1    = page_html.match(/value="(.*?)">.*?体育课</)[1]
    tiyu2    = page_html.match(/value="(.*?)">体育课程</)[1]
    pjfl     = page_html.match(/value="(.*?)">学生评教</)[1]

    picis = [[lilun1, lilun2], [shijian1, shijian2], [tiyu1, tiyu2]]

    # 分批次进入
    picis.each do |pici|
      p = @agent.post('http://211.87.177.1/jwxt/jxpjgl.do?method=queryJxpj&type=xs',
                      'xnxq' => term,
                      'pjpc' => pici[0],
                      'pjfl' => pjfl,
                      'pjkc' => pici[1],
                      'PageNum' => '1')

      page_html = p.search('html').to_s

      # 获取总页码
      pages = page_html.match(/totalPages" value="(.*?)"/)[1].to_i

      1.upto(pages) do |page|
        if page > 1
          p = @agent.post('http://211.87.177.1/jwxt/jxpjgl.do?method=queryJxpj&type=xs',
                          'xnxq' => term,
                          'pjpc' => pici[0],
                          'pjfl' => pjfl,
                          'pjkc' => pici[1],
                          'PageNum' => page.to_s)
          page_html = p.search('html').to_s
        end

        # 老师们一律98，不管提交没提交
        page_html.scan(/JsMods\('(.*?)',/) do |s|
          path = s[0]

          p = @agent.get('http://211.87.177.1' + path)
          options_html = p.search('#table1').to_s
#puts options_html
          # 第一个选 B，其余选 A
          radio = []
          options_html.scan(/radio.h="0"\s*?value="(.*?)"/) do |option|
            radio << option[0]
          end
          radio[0] = options_html.match(/radio1".*?"1"\s*?value="(.*?)"/)[1]

          # tjfs: 保存1，提交2（后面有一处 type 同）
          url = 'http://211.87.177.1/jwxt/jxpjgl.do?method=savePj&tjfs=2&val=' + radio.join('*')

          # 提交
          params = path[16..-1].split('&amp;').collect do |item|
            arr = item.split('=')
            arr[1] = '' if arr.length == 1
            arr
          end.to_h

          params.delete('method')
          params.delete('xnxqid')
          params.merge!({'typejsxs' => 'xs', 'isxytj' => '1', 'pjfl' => '', 'xsflid' => ''})
          params['pj09id'] = '' unless params['pj09id']
          params['type'] = '2'  # 保存1，提交2
          params.merge!(radio.collect.with_index { |val,i| ["radio#{i+1}", val] }.to_h)

          params_str = params.collect { |k,v| "#{k}=#{v}" }.join('&')

          t=[]
          options_html.scan(/name="pj03id"\s*?value="(.*?)"/) do |option|
            t << option[0]
          end
          params_str += t.collect { |s| "&pj03id=#{s}&jynr=&jshfnr=" }.join
          params_str.sub!(',', '%2C')

          # Referer 很重要
          p = @agent.post(url, params_str,
                          'Referer' => 'http://211.87.177.1' + path,
                          'Content-Type' => 'application/x-www-form-urlencoded')
        end
      end
    end
  end
end