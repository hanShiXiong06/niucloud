```
CREATE TABLE `saas_xiaoyuan_class` (                                                                                      
   `id` int(11) NOT NULL AUTO_INCREMENT,                                                                                        
   `site_id` int(11) NOT NULL DEFAULT 0,                                                                                        
   `school_id` int(11) NOT NULL DEFAULT 0,                                                                                      
   `department_id` int(11) NOT NULL DEFAULT 0 COMMENT '院系ID(预留)',                                                           
   `major_id` int(11) NOT NULL DEFAULT 0 COMMENT '专业ID(预留)',                                                                
   `name` varchar(100) NOT NULL DEFAULT '' COMMENT '班级名称',                                                                  
   `code` varchar(50) NULL DEFAULT '' COMMENT '班级代码(bjdm)',                                                                 
   `grade` varchar(20) NULL DEFAULT '' COMMENT '年级(如2024)',                                                                  
   `sort` int(11) NOT NULL DEFAULT 0,                                                                                           
   `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0禁用1启用',                                                                 
   `create_time` int(11) NULL DEFAULT 0,                                                                                        
   `update_time` int(11) NULL DEFAULT 0,                                                                                        
   PRIMARY KEY (`id`),                                                                                                          
   INDEX `school_grade`(`school_id`, `grade`),                                                                                  
   INDEX `site_id`(`site_id`, `status`)                                                                                         
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班级表';   
 
 CREATE TABLE `saas_xiaoyuan_class_schedule` (                                                                             
   `id` int(11) NOT NULL AUTO_INCREMENT,                                                                                        
   `site_id` int(11) NOT NULL DEFAULT 0,                                                                                        
   `school_id` int(11) NOT NULL DEFAULT 0,                                                                                      
   `class_id` int(11) NOT NULL DEFAULT 0,                                                                                       
   `semester` varchar(50) NOT NULL DEFAULT '' COMMENT '学期(2024-2025-1)',                                                      
   `week_day` tinyint(1) NOT NULL DEFAULT 1 COMMENT '星期(1-7)',                                                                
   `section_start` tinyint(2) NOT NULL DEFAULT 1 COMMENT '开始节次',                                                            
   `section_end` tinyint(2) NOT NULL DEFAULT 1 COMMENT '结束节次',                                                              
   `course_code` varchar(50) NULL DEFAULT '' COMMENT '课程代码',                                                                
   `course_name` varchar(100) NOT NULL DEFAULT '' COMMENT '课程名称',                                                           
   `teacher` varchar(50) NULL DEFAULT '' COMMENT '教师',                                                                        
   `classroom` varchar(100) NULL DEFAULT '' COMMENT '教室',                                                                     
   `location` varchar(100) NULL DEFAULT '' COMMENT '校区/地点',                                                                 
   `credit` decimal(3,1) NULL DEFAULT 0.0 COMMENT '学分',                                                                       
   `start_week` int(11) NOT NULL DEFAULT 1,                                                                                     
   `end_week` int(11) NOT NULL DEFAULT 16,                                                                                      
   `week_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0全部1单周2双周',                                                         
   `color` varchar(20) NULL DEFAULT '',                                                                                         
   `weeks_text` varchar(50) NULL DEFAULT '' COMMENT '原始周次文本(如1-14周)',                                                   
   `create_time` int(11) NULL DEFAULT 0,                                                                                        
   `update_time` int(11) NULL DEFAULT 0,                                                                                        
   PRIMARY KEY (`id`),                                                                                                          
   INDEX `class_semester`(`class_id`, `semester`),                                                                              
   INDEX `site_id`(`site_id`)                                                                                                   
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='班级课表';   

ALTER TABLE `saas_xiaoyuan_schedule`                                                                                      
   ADD COLUMN `class_id` int(11) NULL DEFAULT 0 COMMENT '关联班级ID' AFTER `school_id`,                                         
   ADD COLUMN `is_custom` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0使用班级课表 1已自定义' AFTER `schedule_data`;
 UPDATE saas_xiaoyuan_class_schedule SET semester = '2025-2026-2' WHERE semester = '2025-2026-1'; 
 
 ALTER TABLE `saas_xiaoyuan_school`                                                                                                                                                                                                                        
  ADD COLUMN `semester_start` date NULL DEFAULT NULL COMMENT '开学日期' AFTER `lat`,
  ADD COLUMN `semester_end` date NULL DEFAULT NULL COMMENT '结束日期' AFTER `semester_start`,                                                                                                                                                               
  ADD COLUMN `sections` text NULL COMMENT '课节时间段(JSON)' AFTER `semester_end`; 
```

# 注意修改表前缀