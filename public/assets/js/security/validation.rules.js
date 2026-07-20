export const constraints = {
  usernameMin: 3,
  usernameMax: 20,
  passwordMin: 12,
};

export const regex = {
  username: /^(?=.*[a-zA-Z])[a-zA-Z0-9_]{3,20}$/,
  password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{12,}$/,
};

export const messages = {
  required: 'Champ requis',
  email: 'Format d’email invalide',
  username: `Nom invalide (${constraints.usernameMin}-${constraints.usernameMax} caractères, lettres, chiffres, underscore)`,
  password: `Mot de passe faible (${constraints.passwordMin}+ caractères, A-Z, a-z, chiffre, symbole, sans espace)`,
};
