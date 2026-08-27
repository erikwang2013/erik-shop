#!/usr/bin/env node
/**
 * Admin 后台 + Service 页面 E2E 测试（Playwright）
 * 用法: node scripts/e2e/ui-e2e.js [--service-base http://127.0.0.1:8792]
 * 环境: admin 运行于 8790（8788 被其他项目占用），service 运行于 8792（8787 被占用）
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ADMIN_BASE = process.env.ADMIN_BASE || 'http://127.0.0.1:8790';
const SERVICE_BASE = process.env.SERVICE_BASE || 'http://127.0.0.1:8795';
const SHOT_DIR = path.resolve(__dirname, '../../docs/test-reports/screenshots');
const SESSION_DIR = path.resolve(__dirname, '../../admin/runtime/sessions');
const USERNAME = 'admin';
const PASSWORD = 'E2ePass123!';
const ADMIN_SRC = '/home/wwwroot/shop-php/admin';

const results = []; // {name, status: 'PASS'|'FAIL'|'WARN', note, screenshot}
const shot = (page, name) => page.screenshot({ path: path.join(SHOT_DIR, name), fullPage: false });

function log(name, status, note = '', screenshot = '') {
  results.push({ name, status, note, screenshot });
  console.log(`[${status}] ${name}${note ? ' — ' + note : ''}`);
}

function readCaptcha(sid) {
  const file = path.join(SESSION_DIR, 'session_' + sid);
  const content = fs.readFileSync(file, 'utf8');
  const m = content.match(/captcha-login";s:\d+:"([^"]+)"/);
  return m ? m[1] : '';
}

async function getSessionId(page) {
  const cookies = await page.context().cookies();
  const c = cookies.find(c => c.name === 'PHPSID');
  return c ? c.value : null;
}

async function waitCaptcha(page, sid, notEqual) {
  for (let i = 0; i < 20; i++) {
    await page.waitForTimeout(150);
    try {
      const v = readCaptcha(sid);
      if (v && v !== notEqual) return v;
    } catch (e) { /* session 文件尚未落盘 */ }
  }
  return '';
}

async function doLogin(page, { username, password, captcha }) {
  return page.evaluate(async ({ username, password, captcha }) => {
    const res = await fetch('/app/admin/account/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ username, password, captcha }),
    });
    return res.json();
  }, { username, password, captcha });
}

async function loginFlow(browser) {
  const context = await browser.newContext({ viewport: { width: 1600, height: 950 } });
  const page = await context.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(String(e)));

  // ---- 登录页渲染 ----
  await page.goto(ADMIN_BASE + '/app/admin', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name=username]', { timeout: 10000 });
  const title = await page.title();
  log('01-登录页渲染', title.includes('登录') ? 'PASS' : 'FAIL',
    `页面标题=${title}`, await shot(page, '01-login-page.png'));

  // ---- 错误密码提示 ----
  let sid = await getSessionId(page);
  let captcha = await waitCaptcha(page, sid);
  const wrong = await doLogin(page, { username: USERNAME, password: 'WrongPass!', captcha });
  const wrongOk = wrong && wrong.code === 1;
  log('02-错误密码提示', wrongOk ? 'PASS' : 'FAIL',
    `接口返回=${JSON.stringify(wrong && wrong.msg)}`, await shot(page, '02-login-wrong-password.png'));

  // ---- 正确登录（点击验证码触发前端刷新，读取新验证码） ----
  await page.click('.codeImage');
  captcha = await waitCaptcha(page, sid);
  const ok = await doLogin(page, { username: USERNAME, password: PASSWORD, captcha });
  if (ok && ok.code === 0) {
    log('03-正确登录', 'PASS', `msg=${ok.msg} token=${ok.data && ok.data.token}`);
  } else {
    log('03-正确登录', 'FAIL', `接口返回=${JSON.stringify(ok)}`);
    await context.close();
    return null;
  }
  // 前端 popup 成功后 location.reload() -> 主布局
  await page.waitForTimeout(600);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#sideMenu', { timeout: 10000 });
  await page.waitForTimeout(1500);
  const menuCount = await page.locator('#sideMenu a').count();
  const iframeCount = await page.evaluate(() => document.querySelectorAll('iframe').length);
  log('04-登录后主布局', menuCount > 3 && iframeCount > 0 ? 'PASS' : 'FAIL',
    `侧边菜单项=${menuCount} iframe=${iframeCount}`, await shot(page, '04-admin-layout.png'));
  return { context, page, pageErrors };
}

async function dashboardChecks(browser, context) {
  const page = await context.newPage();
  // 仪表盘（登录后默认页 index/dashboard，ECharts 记录图）
  await page.goto(ADMIN_BASE + '/app/admin/index/dashboard', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);
  const dash = await page.evaluate(() => ({
    canvas: document.querySelectorAll('canvas').length,
    text: (document.body.innerText || '').length,
  }));
  log('仪表盘-index/dashboard', dash.canvas > 0 && dash.text > 50 ? 'PASS' : 'FAIL',
    `canvas=${dash.canvas} 文本=${dash.text}B`, await shot(page, '05-dashboard-index.png'));

  // 跨境数据面板（ShopDashboard，ECharts CDN + KPI 卡片）
  await page.goto(ADMIN_BASE + '/app/admin/shop/ShopDashboard/index', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(4000);
  const sd = await page.evaluate(() => {
    const kpi = [...document.querySelectorAll('#kpi-row .value')].map(v => v.textContent.trim());
    return {
      kpi,
      canvas: document.querySelectorAll('canvas').length,
      kpiFilled: kpi.every(v => v && v !== '-'),
    };
  });
  const sdOk = sd.kpiFilled && sd.canvas >= 4;
  log('仪表盘-跨境数据面板', sdOk ? 'PASS' : 'FAIL',
    `KPI=${sd.kpi.join(',')} canvas=${sd.canvas}`, await shot(page, '06-dashboard-shop.png'));
  await page.close();
}

async function menuWalk(browser, context) {
  const page = await context.newPage();
  await page.goto(ADMIN_BASE + '/app/admin', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#sideMenu a', { timeout: 10000 });

  // 获取菜单树（含 value=wa_rules.id 与分组 id，用于按 menu-id 精确点击）
  const tree = await page.evaluate(async () => {
    const res = await fetch('/app/admin/rule/get');
    const json = await res.json();
    const items = [];
    const walk = (nodes, groupValue) => nodes.forEach(it => {
      if (it.type == 0) walk(it.children || [], it.value);
      else items.push({ title: it.title, href: it.href || '', value: it.value, groupValue });
    });
    walk(json.data || [], null);
    return items;
  });
  const pages = tree.filter(it =>
    it.href.startsWith('/app/admin/') && !it.href.includes('/demos/') &&
    !it.href.includes('/plugin/') && it.href !== '/app/admin/account/index' &&
    it.href !== '/app/admin/config/index');
  log('菜单遍历-页面数量', pages.length >= 10 ? 'PASS' : 'FAIL', `共 ${pages.length} 个菜单页`);

  const expanded = new Set();
  const pass = { p: 0, f: 0, warn: 0 };
  const failed = [];

  for (const it of pages) {
    const name = `${it.title}(${it.href.replace('/app/admin/', '')})`;
    const shotName = `page-${String(it.href.replace(/[^a-zA-Z0-9]/g, '-')).slice(0, 60)}.png`;
    let status = 'PASS';
    const notes = [];
    try {
      // 展开分组（按 menu-id）；layui 折叠导航中已展开的组再点击会收起，
      // 因此仅当组处于折叠态（li 无 layui-nav-itemed）时才点击
      if (it.groupValue != null && !expanded.has(it.groupValue)) {
        const g = page.locator(`#sideMenu a[menu-type="0"][menu-id="${it.groupValue}"]`);
        if (await g.count()) {
          const open = await g.evaluate(el => el.parentElement && el.parentElement.classList.contains('layui-nav-itemed'));
          if (!open) {
            await g.first().click();
            await page.waitForTimeout(400);
          }
        }
        expanded.add(it.groupValue);
      }
      // 点击菜单项（按 menu-id）
      await page.locator(`#sideMenu a[menu-id="${it.value}"]`).click();
      // 等待对应 iframe 出现。限定 tab 内容区内的 iframe 轮询：
      // 多 tab 时 pear-admin 会回收/重建 iframe，全量查询会漏掉目标 src（flaky）
      await page.waitForFunction(href =>
        [...document.querySelectorAll('.layui-tab-content iframe')].some(f => (f.src || '').includes(href)),
        it.href, { timeout: 15000 });
      let frame = null;
      for (let i = 0; i < 12 && !frame; i++) {
        frame = page.frames().find(f => (f.url() || '').includes(it.href)) || null;
        if (!frame) await page.waitForTimeout(500);
      }
      if (!frame) throw new Error('iframe 未加载（url 未匹配 ' + it.href + '）');
      await page.waitForTimeout(2500); // 等待 layui table / 页面 JS 渲染
      const info = await frame.evaluate(() => ({
        title: document.title,
        tables: document.querySelectorAll('.layui-table, table').length,
        text: (document.body.innerText || '').trim().length,
        html: document.body.innerHTML,
      }));
      // 服务端错误页检测（500 异常页 / 404 路由不存在）
      if (/ErrorException|SQLSTATE|Fatal error|include\(/.test(info.html)) {
        status = 'FAIL';
        notes.push('服务端500错误页（视图/控制器缺失）');
      } else if (/not found/.test(info.html) && info.text < 60) {
        status = 'FAIL';
        notes.push('404 页面（路由/方法不存在）');
      }
      if (info.text < 30 && info.tables === 0 && status === 'PASS') {
        status = 'FAIL'; notes.push('空白内容');
      }
      if (status === 'PASS') pass.p++; else { failed.push({ name, note: notes.join('；'), info }); pass.f++; }
      log(`页面-${name}`, status, notes.join('；') || `title=${info.title} 表格=${info.tables} 文本=${info.text}B`,
        await shot(page, shotName));
    } catch (e) {
      failed.push({ name, note: String(e).slice(0, 140) });
      pass.f++;
      log(`页面-${name}`, 'FAIL', String(e).slice(0, 140), await shot(page, shotName));
    }
  }
  await page.close();
  return { pass, failed };
}

async function logoutFlow(browser, context) {
  const page = await context.newPage();
  await page.goto(ADMIN_BASE + '/app/admin', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('li.user a', { timeout: 10000 });
  await page.hover('li.user a'); // 展开用户下拉菜单
  await page.waitForSelector('a.logout', { state: 'visible', timeout: 5000 });
  await page.locator('a.logout').first().click();
  await page.waitForTimeout(1500); // 注销成功 popup + reload
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const backToLogin = await page.locator('input[name=username]').count();
  log('登出流程', backToLogin > 0 ? 'PASS' : 'FAIL', '登出后回到登录页', await shot(page, '99-logout.png'));
  await context.close();
}

async function serviceChecks(browser) {
  const page = await browser.newPage();
  // 健康检查
  const health = await page.goto(SERVICE_BASE + '/health', { timeout: 15000 });
  const healthBody = await page.evaluate(() => document.body.innerText.trim());
  const healthOk = health && health.status() === 200 && healthBody.includes('"status":"ok"');
  log('service-健康检查', healthOk ? 'PASS' : 'FAIL', `HTTP=${health && health.status()} body=${healthBody.slice(0, 80)}`,
    await shot(page, 'service-health.png'));

  // 首页（默认路由已禁用，预期 404，如实记录）
  const home = await page.goto(SERVICE_BASE + '/', { timeout: 15000 });
  log('service-首页', 'WARN',
    `HTTP=${home && home.status()}（service 配置了 Route::disableDefaultRoute()，无首页路由，属 API 服务设计）`,
    await shot(page, 'service-home.png'));

  // 公开 API 抽测（无需登录）：商品列表
  const products = await page.goto(SERVICE_BASE + '/api/products', { timeout: 15000 });
  const pBody = await page.evaluate(() => document.body.innerText.trim().slice(0, 60));
  log('service-公开API/商品列表', products && products.status() === 200 ? 'PASS' : 'FAIL',
    `HTTP=${products && products.status()} ${pBody}`, await shot(page, 'service-products.png'));
  await page.close();
}

async function main() {
  fs.mkdirSync(SHOT_DIR, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const runId = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);

  try {
    const logged = await loginFlow(browser);
    if (logged) {
      await dashboardChecks(browser, logged.context);
      const walk = await menuWalk(browser, logged.context);
      await logoutFlow(browser, logged.context);
      results.push({ name: '菜单遍历汇总', status: walk.pass.p + walk.pass.f > 0 ? (walk.pass.f === 0 ? 'PASS' : 'FAIL') : 'FAIL', note: `通过 ${walk.pass.p} / ${walk.pass.p + walk.pass.f}`, screenshot: '' });
    }
    await serviceChecks(browser);
  } catch (e) {
    log('测试执行异常', 'FAIL', String(e).slice(0, 300));
  } finally {
    await browser.close();
  }

  // 输出结果 JSON（报告由父脚本生成）
  fs.writeFileSync(path.join(__dirname, 'results.json'), JSON.stringify({ runId, results }, null, 2));
  const p = results.filter(r => r.status === 'PASS').length;
  const f = results.filter(r => r.status === 'FAIL').length;
  const w = results.filter(r => r.status === 'WARN').length;
  console.log(`\n===== 汇总: PASS=${p} FAIL=${f} WARN=${w} 通过率=${(p / (p + f) * 100).toFixed(1)}% =====`);
  process.exit(f > 0 ? 1 : 0);
}

main();
