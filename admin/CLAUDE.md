# admin/ — 跨境电商管理后台

基于 webman-admin 框架的管理面板。

## 项目约定

### Copyright 头部
所有 PHP 文件必须以 Copyright 头部开头：
```php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
```

### 命名空间与全局函数
- 全局变量/函数前 **不加** 反斜杠 `\`
- admin 插件代码命名空间：`plugin\admin\app\controller`、`plugin\admin\app\model`
- 项目级代码命名空间：`app\controller`、`app\model`

### 配置文件
- 项目配置位于 `config/` 目录
- 插件配置位于 `plugin/admin/config/` 目录
- 每个配置项必须带注释说明

## 架构说明

admin/ 是独立的 webman 项目，安装了 `webman/admin` 插件。
与 service/ 共享同一个 MySQL 数据库，通过 `config/database.php` 配置连接。

### webman-admin 插件结构

```
plugin/admin/
  api/              # 外部 API（Auth, Menu, Install, Middleware）
  app/
    controller/     # 管理控制器（14个内置控制器）
      Base.php      # 基础控制器（json/success/fail 响应）
      Crud.php      # 通用 CRUD 控制器（select/insert/update/delete）
      IndexController.php  # 仪表盘
    model/          # 管理模型
      Base.php      # 基础模型（连接 plugin.admin.mysql）
    common/         # 工具类（Auth, Layui, Tree, Util）
    middleware/     # AccessControl 权限中间件
    view/           # LayUI 视图
  config/           # 插件配置（menu.php 定义菜单结构）
  install.sql       # 安装 SQL（wa_ 前缀系统表）
```

### 扩展 CRUD 控制器模式
所有商城管理控制器继承 `plugin\admin\app\controller\Crud`：
```php
class ShopProductController extends Crud
{
    protected $model = ShopProduct::class;  // 绑定的模型类
    // 可选覆盖的方法：
    // afterQuery($items)    — 查询后处理
    // insertInput($data)    — 插入前处理
    // updateInput($data)    — 更新前处理
    // formatSelect($items)  — 列表格式化
}
```

### 菜单配置
在 `plugin/admin/config/menu.php` 中添加菜单项：
- `title` — 菜单标题
- `key` — 控制器类名
- `icon` — layui-icon 图标名
- `type` — 0=菜单组, 1=单页面
- `weight` — 排序权重

### 跨境管理菜单结构
```
商城管理
  商品管理（+多语言编辑 + 分币种定价）
  分类管理
  SKU管理（+ 分币种价格）
  品牌管理
  评价管理
订单管理
  订单列表（含海关信息/币种）
  支付记录（按区域/支付方式）
  退款审批
海关税务
  HS Code 编码库
  商品HS关联
  关税规则（目的国+HS→税率）
  VAT/IOSS 设置
物流管理
  国际物流商（DHL/UPS/FedEx/EMS/EMS）
  物流分区（按国家分组）
  分区费率阶梯
  海外仓管理
  发货管理（+ HS申报 + 轨迹）
  清关单据（商业发票/装箱单）
营销管理
  优惠券（支持分区限定）
  轮播图（支持区域可见）
  秒杀活动
  拼团活动
运营管理
  国家/地区
  货币汇率
  邮件模板（多语言）
  通知推送
  操作日志
数据分析
  跨境面板（区域销售热力图 + 币种收入占比 + 物流时效）
  订单导出（含HS Code/关税/币种）
  商业发票PDF
  装箱单PDF
  财务报表PDF（分币种汇总）
```
## 国际化 (i18n)
- 翻译文件位于 `plugin/admin/resource/translations/`
- 支持语言：`zh_CN`（默认）、`zh_HK`、`en`、`ja`、`ko`
- LayUI 界面文本通过 `trans()` 函数翻译
- 语言切换按钮位于管理后台顶部导航栏

## 数据库表

| 前缀 | 用途 | 示例 |
|------|------|------|
| `wa_` | webman-admin 系统表 | wa_admins, wa_roles, wa_rules, wa_uploads |
| `erik_` | 商城业务表（45张） | erik_products, erik_orders, erik_hs_codes, erik_shipping_zones, erik_product_translations, erik_product_sku_prices 等 |

## 技术栈

| 包 | 用途 |
|---|------|
| webman/admin ~2.0 | 管理后台框架 |
| webman/database | MySQL ORM |
| illuminate/database ^12 | Laravel 数据库层 |
| layui | 前端 UI（Pear Admin 主题） |
| phpoffice/phpspreadsheet | Excel 导出 |
| erikwang2013/poster-php | 敏感操作随机验证 |
| barryvdh/laravel-dompdf | PDF 导出（商业发票/装箱单） |
| guzzlehttp/guzzle | 物流 API 对接 |

## 命令

```bash
php start.php start         # 启动（开发模式）
php start.php start -d      # 守护进程启动
php start.php stop          # 停止
php start.php reload        # 平滑重启
```
