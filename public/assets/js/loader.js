/**
 * QUACKERY — Simple & Professional Loading Entrance
 */
(function() {
  'use strict';

  const loader = document.getElementById('app-loader');
  if (!loader) return;

  const progressBar = document.getElementById('loader-progress-bar');
  const progressPct = document.getElementById('loader-status-pct');
  const statusText = document.getElementById('loader-status-text');

  let progress = 0;
  let targetProgress = 20;
  let isPageLoaded = false;
  let isCompleted = false;

  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReduced) {
    loader.classList.add('is-loaded');
    setTimeout(() => { loader.style.display = 'none'; }, 200);
    return;
  }

  function updateProgress(val) {
    progress = Math.min(100, Math.max(0, val));
    if (progressBar) progressBar.style.width = `${progress}%`;
    if (progressPct) progressPct.textContent = `${Math.floor(progress)}%`;
  }

  function finishLoading() {
    if (isCompleted) return;
    isCompleted = true;

    updateProgress(100);
    if (statusText) statusText.textContent = 'READY';

    setTimeout(() => {
      loader.classList.add('is-loaded');
      setTimeout(() => {
        loader.style.display = 'none';
      }, 500);
    }, 250);
  }

  // Smooth progress increment
  const interval = setInterval(() => {
    if (progress < targetProgress) {
      const step = Math.max(1, (targetProgress - progress) * 0.2);
      updateProgress(progress + step);
    }

    if (isPageLoaded && targetProgress < 100) {
      targetProgress = 100;
    } else if (progress >= 85 && !isPageLoaded) {
      // Hold briefly at 85-90% until page resources finish
      targetProgress = 90;
    } else if (targetProgress < 85) {
      targetProgress += Math.floor(Math.random() * 15 + 10);
    }

    if (progress >= 100) {
      clearInterval(interval);
      finishLoading();
    }
  }, 40);

  // When page finishes loading
  if (document.readyState === 'complete') {
    isPageLoaded = true;
    targetProgress = 100;
  } else {
    window.addEventListener('load', () => {
      isPageLoaded = true;
      targetProgress = 100;
    });
  }

  // Safety fallback: maximum 2.5s duration under any network conditions
  setTimeout(() => {
    isPageLoaded = true;
    targetProgress = 100;
  }, 2200);
})();
