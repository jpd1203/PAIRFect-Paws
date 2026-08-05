/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/views/**/*.blade.php",
    "./resources/js/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        // Ported 1:1 from the original users.css design tokens
        background: "#FDF9F2",
        "secondary-bg": "#F8FAFC",
        primary: {
          DEFAULT: "#A61D24",
          hover: "#8d171e",
          muted: "#f0d4d4",
        },
        "text-dark": "#2A1D1F",
        "text-muted": "#404040",
        "text-light": "#363636",
        "neutral-light": "#F2EFE8",
        "neutral-medium": "#D1D1CB",
        "accent-light": "#E5D3A2",
        status: {
          "processing-bg": "#FAEEDA",
          "processing-text": "#614E34",
          "adopted-bg": "#E6F1FB",
          "adopted-text": "#2A4877",
          "success-bg": "#E1F5EE",
          "success-text": "#295F51",
          "danger-bg": "#FCEBEB",
          "danger-text": "#773E47",
          "flagged-bg": "#F1EBF9",
          "flagged-text": "#4B3A8E",
          "upcoming-bg": "#E8E8E4",
          "upcoming-text": "#3A3A3A",
        },
      },
      fontFamily: {
        primary: ["Poppins", "sans-serif"],
        secondary: ["Inter", "sans-serif"],
      },
      borderRadius: {
        card: "12px",
        btn: "10px",
      },
      boxShadow: {
        card: "0 2px 8px rgba(0,0,0,.08)",
        modal: "0 15px 40px rgba(0,0,0,.18)",
      },
      width: {
        sidebar: "260px",
      },
      height: {
        topbar: "56px",
      },
    },
  },
  plugins: [],
};
