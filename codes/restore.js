(() => {
  const job = document.getElementById('restore-job');
  if (!job) return;
  const terminal = ['active', 'failed', 'cancelled', 'rolled_back'];
  const message = document.getElementById('restore-poll');
  if (terminal.includes(job.dataset.state)) {
    message.textContent = 'Helper reported a final stage. Review the evidence and owner references above.';
    return;
  }
  let stopped = false;
  async function poll() {
    if (stopped) return;
    try {
      const response = await fetch('Restore.php?job=' + encodeURIComponent(job.dataset.job) + '&view=status', {credentials: 'same-origin', cache: 'no-store'});
      if (response.redirected || !response.ok) throw new Error('unavailable');
      const status = await response.json();
      if (status.state !== job.dataset.state) { stopped = true; window.location.reload(); return; }
      message.textContent = 'Waiting for database maintenance helper. This page checks progress every 3 seconds.';
    } catch (_) {
      stopped = true;
      message.textContent = 'Progress is unavailable during maintenance or your session expired. Reopen this job URL when service returns and sign in again. The durable job continues outside this page.';
      return;
    }
    setTimeout(poll, 3000);
  }
  setTimeout(poll, 3000);
})();
