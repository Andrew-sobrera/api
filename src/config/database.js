require('dotenv').config();

module.exports = {
  dialect: 'mariadb',
  port: 3306,
  host: 'localhost',
  username: 'root',
  password: '',
  database: 'api',
  define: {
    timestamp: true,
    underscored: true,
    underscoredAll: true,
    'createdAt': 'created_at',
    'updatedAt': 'updated_at',
  },
};
