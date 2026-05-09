import { ref } from "vue";

const useQuestion = () => {
  const questionInfo = ref({ questionList: [] });

  const getAnswer = (data: any) => {
    const index = questionInfo.value.questionList.findIndex(
      (item) => item.questionId === data.questionId
    );

    if (data.answerType === 0) {
      // 单选：移除其他选项并添加当前选项
      questionInfo.value.questionList = questionInfo.value.questionList.filter(
        (item) => item.questionId !== data.questionId
      );
      questionInfo.value.questionList.push({
        questionId: data.questionId,
        answerIdList: [data.answerId],
      });
    } else {
      // 多选：利用Set简化选择/取消选择操作
      let question = questionInfo.value.questionList.find(
        (item) => item.questionId === data.questionId
      );
      if (question) {
        const answerSet = new Set(question.answerIdList);
        if (answerSet.has(data.answerId)) {
          answerSet.delete(data.answerId);
        } else {
          answerSet.add(data.answerId);
        }
        question.answerIdList = Array.from(answerSet);
      } else {
        questionInfo.value.questionList.push({
          questionId: data.questionId,
          answerIdList: [data.answerId],
        });
      }
    }
  };

  const isAnswerSelected = (questionId: number, answerId: number) => {
    const question = questionInfo.value.questionList.find(
      (item) => item.questionId === questionId
    );
    return question ? question.answerIdList.includes(answerId) : false;
  };

  return {
    questionInfo,
    getAnswer,
    isAnswerSelected,
  };
};

export default useQuestion;
