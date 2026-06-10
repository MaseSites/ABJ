'use client';

import { useState, useEffect } from 'react';

const BASE = '';

export default function Countdown({ saleEndsAt, heroImage }) {
  const [time, setTime] = useState({ days: '00', hours: '00', mins: '00', secs: '00' });

  useEffect(() => {
    const target = new Date(saleEndsAt).getTime();

    function tick() {
      const diff = target - Date.now();
      if (isNaN(target) || diff <= 0) {
        setTime({ days: '00', hours: '00', mins: '00', secs: '00' });
        return;
      }
      const s = Math.floor(diff / 1000);
      setTime({
        days:  String(Math.floor(s / 86400)).padStart(2, '0'),
        hours: String(Math.floor((s % 86400) / 3600)).padStart(2, '0'),
        mins:  String(Math.floor((s % 3600) / 60)).padStart(2, '0'),
        secs:  String(s % 60).padStart(2, '0'),
      });
    }

    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [saleEndsAt]);

  return (
    <section className="hero">
      {heroImage && (
        <div className="hero-media" aria-hidden="true">
          <img src={`${BASE}${heroImage}`} alt="" className="hero-bg-img" />
        </div>
      )}
      <div className="hero-overlay" aria-hidden="true" />

      <div className="hero-inner">
        <div className="hero-inner-wrap">
          <div>
            <span className="hero-sale-pill">ABJ Collection</span>
            <h1 className="hero-title">
              Defined<br />by the<br /><em>Detail.</em>
            </h1>
            <div className="hero-countdown">
              <div>
                <strong>{time.days}</strong>
                <span>Tage</span>
              </div>
              <div>
                <strong>{time.hours}</strong>
                <span>Std</span>
              </div>
              <div>
                <strong>{time.mins}</strong>
                <span>Min</span>
              </div>
              <div>
                <strong>{time.secs}</strong>
                <span>Sek</span>
              </div>
            </div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '.6rem', paddingBottom: '.4rem' }}>
            <a href="/shop" className="btn btn-line">Shop Now</a>
          </div>
        </div>
      </div>
    </section>
  );
}
