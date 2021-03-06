create database if not exists scit_edu_tool;

use scit_edu_tool;

create table if not exists user_info (
    u_id tinytext not null,
    u_password text not null,
    u_name tinytext,
    u_faculty smallint,
    u_specialty smallint,
    u_class tinyint,
    u_grade smallint,
    u_session tinytext not null,
    u_session_expired int unsigned not null,
    u_token_effective boolean not null
) charset=utf8;

create table if not exists class_schedule (
    t_id tinytext not null,
    t_faculty smallint not null,
    t_specialty smallint not null,
    t_class tinyint not null,
    t_grade smallint not null,
    t_school_year tinytext not null,
    t_semester tinyint not null,
    t_content text not null,
    t_expired int unsigned not null
) charset=utf8;

create table if not exists faculty_chart (
    f_id smallint not null,
    f_name tinytext not null
) charset=utf8;

create table if not exists specialty_chart (
    s_id smallint not null,
    s_name tinytext not null,
    f_id smallint not null
) charset=utf8;

create table if not exists class_chart (
    f_id smallint not null,
    s_id smallint not null,
    c_id tinyint not null,
    c_name tinytext not null
) charset=utf8;

create table if not exists sign_keys (
    app_key tinytext not null,
    app_secret tinytext not null,
    platform tinytext not null,
    mail tinytext not null,
    available tinyint(1) NULL DEFAULT 1
)