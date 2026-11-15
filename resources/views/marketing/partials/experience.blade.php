<section class="section experience" id="experience">
  <div class="wrap experience-grid">
    <div class="experience-intro">
      <span class="eyebrow">A feel for your new routine</span>
      <h2>Clock in. See your rota. Request leave.</h2>
      <p>Choose a role to explore the everyday tools. Every screen below uses sample information only.</p>

      <div class="demo-role-switch" role="group" aria-label="Choose a MentoClock app experience">
        <button class="active" type="button" data-demo-role="employee" aria-pressed="true">Employee app</button>
        <button type="button" data-demo-role="manager" aria-pressed="false">Business owner app</button>
      </div>

      <div class="demo-nav" data-demo-nav="employee" role="group" aria-label="Employee app preview">
        <button class="active" type="button" data-demo-screen="employee-clock" aria-pressed="true"><?=\App\Support\MarketingSite::icon('clock')?>Clock in / out</button>
        <button type="button" data-demo-screen="employee-rota" aria-pressed="false"><?=\App\Support\MarketingSite::icon('check')?>My rota</button>
        <button type="button" data-demo-screen="employee-leave" aria-pressed="false"><?=\App\Support\MarketingSite::icon('people')?>Request holiday</button>
        <button type="button" data-demo-screen="employee-report" aria-pressed="false"><?=\App\Support\MarketingSite::icon('report')?>My hours</button>
      </div>
      <div class="demo-nav" data-demo-nav="manager" role="group" aria-label="Business owner app preview" hidden>
        <button class="active" type="button" data-demo-screen="manager-overview" aria-pressed="true"><?=\App\Support\MarketingSite::icon('people')?>Overview</button>
        <button type="button" data-demo-screen="manager-attendance" aria-pressed="false"><?=\App\Support\MarketingSite::icon('clock')?>Attendance</button>
        <button type="button" data-demo-screen="manager-rota" aria-pressed="false"><?=\App\Support\MarketingSite::icon('check')?>Team rota</button>
        <button type="button" data-demo-screen="manager-leave" aria-pressed="false"><?=\App\Support\MarketingSite::icon('report')?>Holiday requests</button>
        <button type="button" data-demo-screen="manager-report" aria-pressed="false"><?=\App\Support\MarketingSite::icon('report')?>Reports</button>
      </div>
      <p class="caption">Interactive sample only. No attendance, rota or holiday request is saved.</p>
    </div>

    <div class="preview-panel demo-preview" aria-live="polite">
      <div class="preview-top"><span class="preview-dot"></span><span data-demo-heading>EMPLOYEE APP PREVIEW</span><span class="sample-badge">Fictional sample</span></div>

      <div class="demo-screen" data-screen="employee-clock">
        <div class="demo-title"><span>Riverside café</span><small>Monday, 9 November</small></div>
        <div class="demo-clock">
          <span data-clock-status>Ready for your shift</span>
          <strong data-clock-time>09:00</strong>
          <p data-clock-copy>Your assigned workplace is ready. Clock in when you arrive.</p>
          <button id="demo-punch" class="button" type="button">Clock in <?=\App\Support\MarketingSite::icon('arrow')?></button>
        </div>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('pin')?>Location is checked at clock-in and clock-out. The app does not continuously track employees.</div>
      </div>

      <div class="demo-screen" data-screen="employee-rota" hidden>
        <div class="demo-title"><span>My rota</span><small>9–15 November 2026</small></div>
        <div class="demo-branch-label"><?=\App\Support\MarketingSite::icon('shop')?>Riverside café</div>
        <div class="demo-rota-list">
          <div><span>Mon 9 Nov</span><strong>09:00–17:00</strong><small>Published</small></div>
          <div><span>Tue 10 Nov</span><strong>10:00–16:00</strong><small>Published</small></div>
          <div><span>Wed 11 Nov</span><strong>Off</strong><small></small></div>
          <div><span>Thu 12 Nov</span><strong>09:00–17:00</strong><small>Published</small></div>
          <div><span>Fri 13 Nov</span><strong>Off</strong><small></small></div>
        </div>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('check')?>See published shifts and the workplace before your shift starts.</div>
      </div>

      <div class="demo-screen" data-screen="employee-leave" hidden>
        <div class="demo-title"><span>Leave &amp; holidays</span><small>Annual leave</small></div>
        <div class="demo-leave-balance"><strong>27</strong><span>days remaining <small>of 30 days</small></span></div>
        <form class="demo-request-form" data-demo-holiday-form>
          <label>First day<input type="date" value="2026-12-14" required></label>
          <label>Last day<input type="date" value="2026-12-16" required></label>
          <button class="button" type="submit">Preview holiday request <?=\App\Support\MarketingSite::icon('arrow')?></button>
        </form>
        <p class="demo-feedback" data-holiday-feedback role="status" hidden></p>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('check')?>Check your allowance, choose dates and send a request to your manager.</div>
      </div>

      <div class="demo-screen" data-screen="employee-report" hidden>
        <div class="demo-title"><span>My attendance</span><small>This week · sample</small></div>
        <div class="report-summary"><span>Recorded hours</span><strong>31h 30m</strong><small>Breaks shown separately</small></div>
        <div class="demo-rota-list compact"><div><span>Mon 9 Nov</span><strong>7h 30m</strong><small>30m break</small></div><div><span>Tue 10 Nov</span><strong>6h</strong><small>No break</small></div><div><span>Thu 12 Nov</span><strong>8h</strong><small>30m break</small></div></div>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('report')?>Review daily, weekly and monthly hours, then share your report.</div>
      </div>

      <div class="demo-screen" data-screen="manager-overview" hidden>
        <div class="demo-title"><span>Your business at a glance</span><small>Wednesday, 11 November</small></div>
        <div class="manager-metrics">
          <article><small>EMPLOYEES</small><strong>12</strong></article>
          <article><small>WORKPLACES</small><strong>2</strong></article>
          <article><small>CLOCKED IN</small><strong>7</strong></article>
          <article><small>HOLIDAY REQUESTS</small><strong>2 waiting</strong></article>
        </div>
        <div class="manager-quick-item"><span><?=\App\Support\MarketingSite::icon('check')?></span><div><strong>Review holiday requests</strong><small>Approve or decline employee requests</small></div></div>
        <div class="manager-quick-item"><span><?=\App\Support\MarketingSite::icon('report')?></span><div><strong>Prepare an hours report</strong><small>Review attendance before sharing with your accountant</small></div></div>
      </div>

      <div class="demo-screen" data-screen="manager-attendance" hidden>
        <div class="demo-title"><span>Today’s attendance</span><small>Riverside café · 11 Nov</small></div>
        <div class="manager-attendance-list">
          <article><div><strong>Alex Taylor</strong><small>Clocked in · 09:02</small></div><span class="manager-status">On shift</span></article>
          <article><div><strong>Jamie Patel</strong><small>Clocked out · 14:18</small></div><button class="demo-action" type="button" data-demo-action="Sample record opened. Corrections are recorded in the real app’s audit history.">Review record</button></article>
          <article><div><strong>Sam Morgan</strong><small>Clocked out · 16:05</small></div><span class="manager-status">Ready to review</span></article>
        </div>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('check')?>Review attendance, correct a clock time and finalise completed records.</div>
        <p class="demo-feedback" data-manager-feedback role="status" hidden></p>
      </div>

      <div class="demo-screen" data-screen="manager-rota" hidden>
        <div class="demo-title"><span>Team rota</span><small>9–15 November 2026</small></div>
        <label class="demo-branch-select">Branch<select aria-label="Select sample branch"><option>Riverside café</option><option>Central kitchen</option></select></label>
        <div class="manager-rota-table" role="table" aria-label="Sample weekly rota">
          <div class="manager-rota-row manager-rota-head" role="row"><span role="columnheader">Employee</span><span role="columnheader">Mon</span><span role="columnheader">Tue</span><span role="columnheader">Wed</span><span role="columnheader">Thu</span><span role="columnheader">Fri</span></div>
          <div class="manager-rota-row" role="row"><strong role="cell">Alex</strong><span role="cell">09–17</span><span role="cell">09–17</span><span role="cell">Off</span><span role="cell">09–17</span><span role="cell">10–16</span></div>
          <div class="manager-rota-row" role="row"><strong role="cell">Jamie</strong><span role="cell">10–16</span><span role="cell">Off</span><span role="cell">09–17</span><span role="cell">09–17</span><span role="cell">09–17</span></div>
          <div class="manager-rota-row" role="row"><strong role="cell">Sam</strong><span role="cell">Off</span><span role="cell">09–17</span><span role="cell">09–17</span><span role="cell">10–16</span><span role="cell">09–17</span></div>
        </div>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('check')?>Switch branch, review the week and manage upcoming shifts in the app.</div>
      </div>

      <div class="demo-screen" data-screen="manager-leave" hidden>
        <div class="demo-title"><span>Holiday requests</span><small>2 waiting for review</small></div>
        <article class="manager-leave-card"><div><strong>Alex Taylor</strong><small>Annual leave · 14–16 Dec · 3 working days</small></div><span class="pending-pill">Pending</span><div class="manager-leave-actions"><button class="demo-action" type="button" data-demo-action="Sample request declined. No real employee request was changed.">Decline</button><button class="demo-action approve" type="button" data-demo-action="Sample request approved. No real employee request was changed.">Approve</button></div></article>
        <article class="manager-leave-card"><div><strong>Jamie Patel</strong><small>Annual leave · 21 Dec · 1 working day</small></div><span class="pending-pill">Pending</span></article>
        <p class="demo-feedback" data-manager-feedback role="status" hidden></p>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('check')?>Review requests against leave allowance and rota cover.</div>
      </div>

      <div class="demo-screen" data-screen="manager-report" hidden>
        <div class="demo-title"><span>Branch hours report</span><small>Riverside café · 9–15 Nov</small></div>
        <div class="report-summary"><span>Team hours to review</span><strong>118h 30m</strong><small>Worked 112h · paid leave 6h 30m</small></div>
        <div class="manager-report-list"><div><strong>Alex Taylor</strong><span>38h 30m</span><small>Worked 32h · leave 6h 30m</small></div><div><strong>Jamie Patel</strong><span>40h</span><small>Worked 40h · leave 0h</small></div><div><strong>Sam Morgan</strong><span>40h</span><small>Worked 40h · leave 0h</small></div></div>
        <button class="demo-action" type="button" data-demo-action="Sample branch report prepared. The real app lets managers download or share reports.">Preview branch report</button>
        <p class="demo-feedback" data-manager-feedback role="status" hidden></p>
        <div class="demo-note"><?=\App\Support\MarketingSite::icon('report')?>Choose a branch and date range, review staff totals and share a PDF or CSV.</div>
      </div>
    </div>
  </div>
</section>
