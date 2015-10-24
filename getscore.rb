#!/usr/bin/env ruby
require 'rubygems'
#require 'byebug'
require './lib/upc_crawler'

if ARGV.length > 0
  students=[]
  File.open(ARGV[0], 'r') do |f|
    # 备注：读出来的数据是带回车的
    students << f.readline.split(':') until f.eof?
  end
  out_file = ARGV[1] || 'output.csv'
else
  print '账号：'
  user = gets.chomp
  print '密码：'
  password = gets.chomp
  print '输出到（output.csv）：'
  out_file = gets.chomp

  students = [[user, password]]
  out_file = 'output.csv' if out_file == ''
end

error_list = []

File.open(out_file,'w') do |file|
  file.puts "序号,学号,姓名,开课学期,课程名称,总成绩,成绩标志,课程性质,课程类别,学时,学分,考试性质,补重学期"

  students.each do |student|
    puts "正在处理#{student[0]}..."
    begin
      stu = UpcCrawler.new(student[0], student[1].chomp)
      scores = stu.moron.scores_array
      scores.each do |score|
        file.puts score.join(',')
      end
    rescue => e
      puts "#{student[0]}时出现错误: #{e.message}"
      error_list << student[0]
    end
  end
end

if error_list.length > 0
  puts "处理以下学生时出现错误："
  error_list.each { |item| puts item }
end
