import Tarefa from '../models/Tarefa';

class TaskController {
  async findAll(req, res) {
    const tarefas = await Tarefa.findAll();
    res.json(tarefas);
  }

  async create(req, res) {
    const novaTarefa = await Tarefa.create(req.body);

    res.json(novaTarefa);
  }
}

export default new TaskController();
