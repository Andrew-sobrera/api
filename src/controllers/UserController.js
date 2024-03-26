import User from '../models/User';
import tokenController from './TokenController';
import userFormatter from '../formatter/userFormatter'
import userResource from './resources/userResource';

class UserController {
  async create(req, res) {
    try {
      const { nome, email, password } = req.body

      const token = await tokenController.store(email, password);

      const userObj = userFormatter(nome,password,email,token);

      const user = await User.create(userObj);

      res.json(userResource(user));
    } catch (e) {
      res.status(400).json({
        erros: e.errors.map((err) => err.message),
      });
    }
  }

  async findAll(req, res) {
    try {
      const users = await User.findAll();
      return res.json(users);
    } catch (errr) {
      res.status(400).json({
        erros: e.errors.map((err) => err.message),
      });
    }
  }

  async show(req, res) {
    try {
      const { id } = req.params;

      const users = await User.findByPk(id);
      return res.json(users);
    } catch (err) {
      res.status(400).json({
        erros: e.errors.map((err) => err.message),
      });
    }
  }

  async update(req, res) {
    try {
      const { id } = req.params;

      if(!id){
        return res.status(400).json({
          erros: ['Id não enviado']
        })
      }

      const user = await User.findByPk(id);

      if(!user){
        return res.status(400).json({
          erros: ['usuário não existe']
        })
      }

      const userUpdate = await user.update(req.body)

      return res.json(userUpdate);
    } catch (e) {
      res.status(400).json({
        erros: e.errors.map((err) => err.message),
      });
    }
  }

  async destroy(req,res){
    try {
      const { id } = req.params;

      if(!id){
        return res.status(400).json({
          erros: ['Id não enviado']
        })
      }

      const user = await User.findByPk(id);

      if(!user){
        return res.status(400).json({
          erros: ['usuário não existe']
        })
      }

      const userdelete = await user.destroy()

      return res.status(200).json('success');
    } catch (e) {
      res.status(400).json({
        erros: e.errors.map((err) => err.message),
      });
    }
  }
}

export default new UserController();
