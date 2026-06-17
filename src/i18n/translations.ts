// Note: Keep a runtime helper (not used during translation creation)
// so age can be computed on the client instead of build time.
export function getAge(birthDate: Date) {
  const today = new Date();
  let years = today.getFullYear() - birthDate.getFullYear();
  const hasHadBirthdayThisYear =
  today.getMonth() > birthDate.getMonth() ||
  (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());
  if (!hasHadBirthdayThisYear) years--;
  const msPerDay = 1000 * 60 * 60 * 24;
  const days = Math.floor((today.getTime() - birthDate.getTime()) / msPerDay);
  return { years, days };
}

export const translations = {
  de: {
    nav: {
      home: 'Start',
      about: 'Über mich',
      projects: 'Projekte',
      skills: 'Fähigkeiten',
    },
    about: {
      title: 'Michael Neuhold',
      // Build-time neutral parts; dynamic age will be injected client-side
      bio_generel_prefix: 'Ich bin',
      bio_generel_join: 'Jahre alt und',
      age_days_label: 'Tage',
      bio_generel_after: 'bin momentan im Maturajahrgang der HTBL Kapfenberg und studiere bereits seit der 3. Klasse Oberstufe Software Engineering and Management auf der TU Graz als außerordentlicher Student.',
      bio_generell_link: '(mehr zu meiner Ausbildung)',
      bio_triathlon: `Ich bin begeisterter AgeGroup Triathlet und bin bereits mehrfacher steirischer Meister im Triathlon und im Crosslauf in meiner Altersklasse.
      Zusätzlich habe ich mich nun für die Europa- und Weltmeisterschaft auf der Olympischen Distanz 2026 in Spanien qualifiziert.`,
      bio_triathlon_link2: '(alle meine bestrittenen Rennen)',
      bio_triathlon_link: '(mehr zu meiner sportlichen Erfolgen)',
      bio_work: `Derzeit arbeite ich als Teaching Assistant an der TU Graz und habe bereits mehrere Praktika im IT-Sektor absolviert.`,
      bio_work_link: '(mehr zu meiner beruflichen Erfahrung)',
      sponsoring: `Aufgrund meiner großen Leidenschaft für den Triathlonsport fließt hier sehr viel Geld für Verpfelgung, mehrfach jährlich neue Laufschuhe, alle paar Jahre
      ein neues Renn- bzw TT-Rad und vorallem Nenn/Startgeld und Reisen und Unterbringungen.`,
      sponsoring_anfrage: `Wenn Interesse an einem Sponsoring besteht, finden Sieh hier meine Sponsormappe für weitere Informationen.`,
      sponsoring_link_text: 'Sponsormappe (PDF anzeigen)',
    },
    sections: {
      education: 'Ausbildung & Erfolge',
      projects: 'Projekte',
      skills: 'Fähigkeiten',
    },
    common: {
      readMore: 'Mehr erfahren',
      contact: 'Kontakt',
    }
  },
  en: {
    nav: {
      home: 'Home',
      about: 'About',
      projects: 'Projects',
      skills: 'Skills',
    },
    about: {
      title: 'Michael Neuhold',
      bio_generel_prefix: 'I am',
      // Build-time neutral parts; dynamic age will be injected client-side
      bio_generel_join: 'years old and',
      age_days_label: 'days',
      bio_generel_after: 'currently in my final year at HTBL Kapfenberg.',
      bio_generell_link: '(more about my education)',
      bio_triathlon: `I have also been studying Software Engineering and Management at TU Graz as a non-matriculated student since the 3rd year of high school.`,
      bio_triathlon_link: '(all my races)',
      bio_triathlon_link2: '(more about my achievements)',
      bio_work: `I am currently working as a Teaching Assistant at TU Graz and have already completed several internships in the IT sector.`,
      bio_work_link: '(more about my professional experience)',
      sponsoring: `Due to my great passion for triathlon, a lot of money goes into nutrition, multiple new running shoes annually, and a new racing or TT bike every few years.`,
      sponsoring_anfrage: `If you are interested in sponsoring me, please find my sponsorship brochure here for more information.`,
      sponsoring_link_text: 'Sponsorship brochure (view PDF)',
    },
    sections: {
      education: 'Education & Achievements',
      projects: 'Projects',
      skills: 'Skills',
    },
    common: {
      readMore: 'Read more',
      contact: 'Contact',
    }
  }
} as const;

export type Locale = keyof typeof translations;

export function useTranslations(locale: Locale) {
  return translations[locale];
}
