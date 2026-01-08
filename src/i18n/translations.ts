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
      bio1: 'Ich bin Mechatronik-Student an der HTBL Kapfenberg (seit 2021) und außerordentlicher Student an der TU Graz für Software Engineering and Management (seit 2023). Neben meinem Studium arbeite ich als Teaching Assistant an der TU Graz und als Tutor an der HTBL Kapfenberg.',
      bio2: 'Als begeisterter Triathlet habe ich mehrere steirische Meistertitel in verschiedenen Altersklassen errungen. Sport und Technik sind meine Leidenschaft.',
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
      bio1: 'I am a Mechatronics student at HTBL Kapfenberg (since 2021) and an extraordinary student at TU Graz for Software Engineering and Management (since 2023). In addition to my studies, I work as a Teaching Assistant at TU Graz and as a tutor at HTBL Kapfenberg.',
      bio2: 'As an enthusiastic triathlete, I have won several Styrian championship titles in various age groups. Sports and technology are my passions.',
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
