import User from '../models/User';

class UserController {
  async create(req, res) {
    try {
      const novaTarefa = await User.create(req.body);

      res.json(novaTarefa);
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
    } catch (e) {
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
    } catch (e) {
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
